/* OSVSuche – Sofort-Suche am Ceres-Suchfeld.
 * Grundsatz: nie schlechter als heute. Die Ceres-Vorschlaege werden erst ausgeblendet,
 * wenn unser Index geladen ist und es Treffer gibt. Sonst bleibt alles wie bisher.
 * Kein MutationObserver, kein Vue.component, kein ceresStore (siehe CERES_FRONTEND.md). */
(function () {
  "use strict";
  if (window.OSVSuche) return;
  var INDEX_URL = "/rest/osv-suche/index", PREIS_URL = "/rest/osv-suche/preise", SUCH_URL = "/artikelsuchergebnisse/?query=";
  var CACHE_KEY = "osvsuche_index_v1", CACHE_MS = 60 * 60 * 1000, MAX = 8;
  var st = { laden: null, ms: null, docs: null, byId: null, cfg: null, panel: null, input: null, sel: -1, items: [], q: "" };

  // ---------- Wortaufbereitung ----------
  var baseTok = MiniSearch.getDefault("tokenize");
  function tokenize(t) { return baseTok(String(t).replace(/(\d)([a-zA-ZäöüÄÖÜß])/g, "$1 $2").replace(/([a-zA-ZäöüÄÖÜß])(\d)/g, "$1 $2")); }
  function norm(t) { return t.toLowerCase().replace(/ä/g, "ae").replace(/ö/g, "oe").replace(/ü/g, "ue").replace(/ß/g, "ss"); }
  function stem(t) {
    if (t.length <= 4) return t;
    t = t.replace(/maenn(chen|el|lein|er)?$/, "mann").replace(/([^n])man$/, "$1mann");
    if (/mann$/.test(t)) return t;
    return t.replace(/(oegen|ogen)$/, "ogen").replace(/(chen|innen|ern|en|er|e|n|s)$/, "");
  }
  var STOP = {}; "mit und fuer der die das den dem des ein eine einer einem eines aus von vom zum zur im in am an auf ohne als oder ganz sehr cm mm m hoch gross grosse grosser kleine klein kleiner neu neue neuer nr stueck stk inh ek e.k gmbh kg eg co erzgeb original".split(" ").forEach(function (w) { STOP[w] = 1; });
  var SYN = {}, HERKUNFT = {}, EIGEN = {}, ABW = [], VOCAB = {}, TEILE = {}, VFREQ = {}, VSHOW = {}, LOGMAX = 1;

  function processTerm(t) {
    var x = norm(t).replace(/[.,;:!?()]/g, "");
    if (x.length < 2 || STOP[x]) return null;
    x = SYN[x] || SYN[stem(x)] || x;
    return x.split(" ").map(stem);
  }
  function teile(d) {
    var out = [];
    ["n", "v", "a"].forEach(function (f) {
      tokenize(d[f] || "").forEach(function (tok) {
        var r = processTerm(tok); if (!r) return;
        r.forEach(function (w) { if (w.length >= 11) for (var i = 4; i <= w.length - 6; i++) out.push(w.slice(i)); });
      });
    });
    return out.join(" ");
  }
  function queryTerm(t) {
    var r = processTerm(t); if (!r) return null;
    var out = [];
    r.forEach(function (w) {
      if (VOCAB[w] || w.length < 7 || /\d/.test(w)) { out.push(w); return; }
      var split = null, i, a, b, a2, A, B;
      for (i = 4; i <= w.length - 4 && !split; i++) {
        a = w.slice(0, i); b = w.slice(i); a2 = a.replace(/(s|n|en|e)$/, "");
        A = VOCAB[stem(a)] ? stem(a) : (VOCAB[stem(a2)] ? stem(a2) : (SYN[a] ? stem(SYN[a]) : (SYN[a2] ? stem(SYN[a2]) : null)));
        B = VOCAB[stem(b)] ? stem(b) : null;
        if (A && B) split = [A, B];
      }
      if (!split) for (i = 4; i <= w.length - 5 && !split; i++) {
        a = w.slice(0, i).replace(/(s|n|en|e)$/, ""); b = stem(w.slice(i));
        if ((STOP[a] || STOP[a + "e"]) && VOCAB[b]) split = [b];
      }
      if (split) out.push.apply(out, split); else out.push(w);
    });
    return out;
  }
  function dl(a, b) {
    var m = a.length, n = b.length, d = [], i, j, c;
    if (Math.abs(m - n) > 3) return 9;
    for (i = 0; i <= m; i++) d[i] = [i];
    for (j = 0; j <= n; j++) d[0][j] = j;
    for (i = 1; i <= m; i++) for (j = 1; j <= n; j++) {
      c = a[i - 1] === b[j - 1] ? 0 : 1;
      d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + c);
      if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
    }
    return d[m][n];
  }
  function istAnfang(w) { if (w.length < 3) return true; for (var v in VOCAB) if (v.indexOf(w) === 0) return true; return false; }
  function bekannt(w) { return VOCAB[w] || TEILE[w] || /\d/.test(w) || istAnfang(w); }
  function korrektur(w) {
    if (bekannt(w) || w.length < 5) return null;
    var max = w.length >= 12 ? 3 : (w.length >= 9 ? 2 : 1), best = null, bd = 99, bl = 99, bf = 0;
    for (var v in VOCAB) {
      if (v.slice(0, 2) !== w.slice(0, 2)) continue;
      var ld = Math.abs(v.length - w.length), dd = ld > 2 ? 99 : dl(w, v);
      if (v.length >= w.length) {
        // Wortanfang mit Tippfehler ("herrenhu" -> "herrnhut"): gegen gleich lange Anfaenge vergleichen,
        // bei gleichem Abstand gewinnt das kuerzere Wort ("nussknacker" vor "nussknackerwerkstatt")
        for (var k = -1; k <= 1; k++) { var pre = v.slice(0, w.length + k); if (pre.length >= 4) { var dp = dl(w, pre), lp = (v.length - w.length) / 100; if (dp < dd || (dp === dd && lp < ld)) { dd = dp; ld = lp; } } }
      }
      if (dd > max) continue;
      if (dd < bd || (dd === bd && (ld < bl || (ld === bl && VFREQ[v] > bf)))) { best = v; bd = dd; bl = ld; bf = VFREQ[v]; }
    }
    return best;
  }
  function meinten(q) {
    var terms = [], weg = [], geaendert = false;
    tokenize(q).forEach(function (tok) {
      var r = queryTerm(tok); if (!r) return;
      r.forEach(function (w) {
        if (bekannt(w)) { terms.push(w); return; }
        var c = korrektur(w);
        if (c) { terms.push(c); geaendert = true; } else { weg.push(tok); geaendert = true; }
      });
    });
    return geaendert ? { terms: terms, weg: weg } : null;
  }

  // ---------- Index ----------
  function aufbauen(data) {
    var cfg = data._cfg || {};
    st.cfg = cfg; SYN = {}; HERKUNFT = {}; EIGEN = {}; ABW = [];
    var s = cfg.synonyme || {};
    Object.keys(s).forEach(function (a) { var k = norm(a), z = norm(s[a]); SYN[k] = z; SYN[stem(k)] = z; });
    (cfg.herkunft || []).forEach(function (w) { HERKUNFT[stem(norm(w))] = 1; });
    (cfg.eigenmarken || []).forEach(function (h) { EIGEN[h] = 1; });
    ABW = (cfg.abwerten || []).map(function (a) { return a.toLowerCase(); });
    var docs = data.docs || [];
    st.docs = docs; st.byId = {};
    var maxvk = 0;
    docs.forEach(function (d) { st.byId[String(d.id)] = d; if ((d.vk || 0) > maxvk) maxvk = d.vk; });
    LOGMAX = Math.log(1 + maxvk) || 1;
    VOCAB = {}; TEILE = {}; VFREQ = {}; VSHOW = {};
    docs.forEach(function (d) {
      teile(d).split(" ").forEach(function (x) { if (x) TEILE[x] = 1; });
      ["n", "v", "a", "h", "kat"].forEach(function (f) {
        tokenize(d[f] || "").forEach(function (t) {
          var r = processTerm(t); if (!r) return;
          r.forEach(function (w) {
            VOCAB[w] = 1; VFREQ[w] = (VFREQ[w] || 0) + 1;
            var eigen = stem(norm(t)) === w;
            if (!VSHOW[w] || (eigen && !VSHOW[w + "#"])) VSHOW[w] = t.toLowerCase();
            if (eigen) VSHOW[w + "#"] = 1;
          });
        });
      });
    });
    st.ms = new MiniSearch({
      idField: "id", fields: ["n", "v", "a", "nr", "h", "kat", "t"], storeFields: ["i", "h", "vk"],
      processTerm: processTerm, tokenize: tokenize,
      extractField: function (d, f) { return f === "t" ? teile(d) : (d[f] == null ? "" : String(d[f])); },
      searchOptions: { processTerm: queryTerm, tokenize: tokenize }
    });
    st.ms.addAll(docs);
  }
  function laden() {
    if (st.laden) return st.laden;
    st.laden = new Promise(function (ok, fehler) {
      try {
        var c = JSON.parse(localStorage.getItem(CACHE_KEY) || "null");
        if (c && c.t && Date.now() - c.t < CACHE_MS && c.d && c.d.docs) { aufbauen(c.d); return ok(); }
      } catch (e) { /* kein Speicher, egal */ }
      fetch(INDEX_URL, { credentials: "same-origin" }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function (d) {
          if (!d || !d.docs || !d.docs.length) throw new Error("leer");
          aufbauen(d);
          try { localStorage.setItem(CACHE_KEY, JSON.stringify({ t: Date.now(), d: d })); } catch (e) { /* zu gross oder gesperrt */ }
          ok();
        }).catch(function (e) { st.laden = null; fehler(e); });
    });
    return st.laden;
  }

  // ---------- Suche ----------
  function fuzzy(t) {
    var lvl = st.cfg.toleranz == null ? 2 : st.cfg.toleranz;
    if (/\d/.test(t) || lvl === 0) return 0;
    if (t.length >= 9 && lvl === 2) return 2;
    return t.length >= 5 ? 1 : 0;
  }
  function suchen(q) {
    var cfg = st.cfg, eb = (cfg.eigenmarkenBonus || 0) / 100, vb = (cfg.verkaufsBonus || 0) / 100, af = (cfg.abwertFaktor || 100) / 100;
    var opts = function (mode) {
      return {
        boostTerm: function (term) { return HERKUNFT[term] ? 0.15 : 1; },
        boost: { n: 3, v: 1.2, a: 1, kat: 4, h: 1, nr: 4, t: 0.4 },
        prefix: function (t) { return t.length >= 3 && !/^\d+$/.test(t); },
        fuzzy: fuzzy, combineWith: mode,
        boostDocument: function (id, term, sf) {
          if (!sf) return 1;
          var f = EIGEN[sf.h] ? 1 + eb : 1;
          f *= 1 + vb * Math.log(1 + (sf.vk || 0)) / LOGMAX;
          var d = st.byId[String(id)], k = d ? ((d.kat || "") + " " + (d.n || "")).toLowerCase() : "";
          if (k && ABW.some(function (a) { return k.indexOf(a) >= 0; })) f *= af;
          return f;
        }
      };
    };
    var toks = tokenize(q).filter(function (t) { var r = queryTerm(t); return r && r.length; });
    var kern = toks.filter(function (t) { return !queryTerm(t).every(function (w) { return HERKUNFT[w]; }); });
    var herk = toks.filter(function (t) { return queryTerm(t).every(function (w) { return HERKUNFT[w]; }); });
    var basis = kern.length ? kern : toks, qk = basis.join(" ");
    if (!qk) return [];
    var r = st.ms.search(qk, opts("AND"));
    if (!r.length && basis.length > 1) {
      var need = Math.ceil(basis.length * 0.5);
      r = st.ms.search(qk, opts("OR")).filter(function (x) { return x.queryTerms.length >= need; });
    }
    if (kern.length && herk.length && r.length) {
      var hit = {}; st.ms.search(herk.join(" "), opts("OR")).forEach(function (x) { hit[x.id] = 1; });
      r.forEach(function (x) { if (hit[x.id]) x.score *= 1.15; });
      r.sort(function (a, b) { return b.score - a.score; });
    }
    var woerter = tokenize(q).map(function (t) { return stem(norm(t)); }).filter(function (w) { return w.length >= 4 && !HERKUNFT[w]; });
    if (woerter.length && r.length) {
      r.forEach(function (x) {
        var d = st.byId[String(x.id)]; if (!d) return;
        var name = norm((d.n || "") + " " + (d.v || ""));
        if (woerter.every(function (w) { return name.indexOf(w) >= 0; })) x.score *= 1.3;
      });
      r.sort(function (a, b) { return b.score - a.score; });
    }
    var seen = {}, out = [];
    r.forEach(function (x) { var d = st.byId[String(x.id)]; if (!d) return; if (!seen[d.i]) { seen[d.i] = { d: d, n: 1 }; out.push(seen[d.i]); } else seen[d.i].n++; });
    return out;
  }

  // ---------- Anzeige ----------
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]; }); }
  function hl(text, q) {
    var t = esc(text), ws = q.split(/\s+/).filter(function (w) { return w.length >= 3; }).map(function (w) { return esc(w).replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); });
    return ws.length ? t.replace(new RegExp("(" + ws.join("|") + ")", "gi"), "<mark>$1</mark>") : t;
  }
  function panelFuer(input) {
    var host = input.parentElement;
    if (!st.panel) { st.panel = document.createElement("div"); st.panel.className = "osvs-panel"; st.panel.setAttribute("role", "listbox"); }
    if (st.panel.parentElement !== host) host.appendChild(st.panel);
    return st.panel;
  }
  // Am Handy ist der Suchkasten schmaler als der Bildschirm: Liste auf volle Breite (12 px Rand) ziehen
  function breite(p) {
    var host = p.parentElement; if (!host) return;
    if (window.innerWidth < 768) {
      var r = host.getBoundingClientRect();
      p.style.left = (12 - r.left) + "px"; p.style.right = "auto"; p.style.width = (window.innerWidth - 24) + "px";
    } else { p.style.left = "0"; p.style.right = "0"; p.style.width = ""; }
  }
  function schliessen() { if (st.panel) st.panel.style.display = "none"; document.body.classList.remove("osvs-zeigt"); st.sel = -1; st.items = []; }
  function zeigen(input, q) {
    st.input = input; st.q = q;
    if (q.length < 2) { schliessen(); return; }
    var hinweis = "", items = [], k = meinten(q);
    if (k && k.terms.length) {
      items = suchen(k.terms.join(" "));
      if (items.length) hinweis = "Ergebnisse für <b>" + esc(k.terms.map(function (w) { return VSHOW[w] || w; }).join(" ")) + "</b>" + (k.weg.length ? " (ohne „" + esc(k.weg.join(" ")) + "“)" : "");
    } else if (!k) items = suchen(q);
    if (!items.length) {
      // Wer gerade weitertippt ("herrenu" nach "herren"), behaelt die letzte Liste; sonst Ceres-Rueckfall
      if (st.letzteQ && (q.indexOf(st.letzteQ) === 0 || st.letzteQ.indexOf(q) === 0) && st.panel && st.panel.style.display === "block") return;
      schliessen(); return;
    }
    st.letzteQ = q;
    var p = panelFuer(input), list = items.slice(0, MAX);
    st.items = list; st.sel = -1;
    p.innerHTML = (hinweis ? '<div class="osvs-hinweis">' + hinweis + "</div>" : "") +
      list.map(function (x, i) {
        var d = x.d;
        return '<a class="osvs-hit" role="option" data-i="' + i + '" href="' + esc(d.u) + '">' +
          '<img src="' + esc(d.b) + '" alt="" loading="lazy" width="48" height="48">' +
          '<span class="osvs-txt"><span class="osvs-n">' + hl(d.n, q) + '</span><span class="osvs-v">' + hl(d.v || d.a || "", q) +
          (x.n > 1 ? " · +" + (x.n - 1) + " weitere Ausführungen" : "") + "</span></span>" +
          '<span class="osvs-p" data-id="' + d.id + '">' + esc(d.p) + "</span></a>";
      }).join("") +
      '<a class="osvs-alle" href="' + SUCH_URL + encodeURIComponent(q) + '">Alle Ergebnisse anzeigen (' + items.length + ") →</a>";
    p.style.display = "block";
    breite(p);
    document.body.classList.add("osvs-zeigt");
    preiseNachladen(list);
  }
  var preisTimer = null;
  function preiseNachladen(list) {
    clearTimeout(preisTimer);
    preisTimer = setTimeout(function () {
      var ids = list.map(function (x) { return x.d.id; }).join(",");
      fetch(PREIS_URL + "?ids=" + ids, { credentials: "same-origin" }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
        if (!j || !j.preise || !st.panel) return;
        Object.keys(j.preise).forEach(function (id) {
          var e = st.panel.querySelector('.osvs-p[data-id="' + id + '"]'), w = j.preise[id];
          if (!e || !w) return;
          if (w.p && e.textContent !== w.p) e.textContent = w.p;
          if (w.uvp && w.uvp !== w.p) e.innerHTML = '<s class="osvs-uvp">' + esc(w.uvp) + "</s> " + esc(w.p);
        });
      }).catch(function () { /* Preis aus dem Index bleibt stehen */ });
    }, 150);
  }
  function markieren(i) {
    if (!st.panel) return;
    var hits = st.panel.querySelectorAll(".osvs-hit");
    st.sel = Math.max(-1, Math.min(hits.length - 1, i));
    for (var k = 0; k < hits.length; k++) hits[k].classList.toggle("osvs-sel", k === st.sel);
  }

  // ---------- Ereignisse (Delegation, auch fuer das Handy-Suchfeld) ----------
  function istSuchfeld(e) { return e && e.matches && e.matches("input.search-input"); }
  var tippTimer = null;
  document.addEventListener("focusin", function (ev) { if (istSuchfeld(ev.target)) laden().catch(function () {}); }, true);
  document.addEventListener("input", function (ev) {
    var t = ev.target; if (!istSuchfeld(t)) return;
    clearTimeout(tippTimer);
    tippTimer = setTimeout(function () {
      laden().then(function () {
        document.body.classList.add("osvs-bereit");
        zeigen(t, t.value.trim());
      }).catch(function () { document.body.classList.remove("osvs-bereit"); });
    }, 60);
  }, true);
  document.addEventListener("keydown", function (ev) {
    var t = ev.target; if (!istSuchfeld(t) || !st.panel || st.panel.style.display !== "block") return;
    if (ev.key === "ArrowDown") { ev.preventDefault(); markieren(st.sel + 1); }
    else if (ev.key === "ArrowUp") { ev.preventDefault(); markieren(st.sel - 1); }
    else if (ev.key === "Escape") { schliessen(); }
    else if (ev.key === "Enter" && st.sel >= 0) {
      ev.preventDefault(); ev.stopImmediatePropagation();
      st.gehe = st.items[st.sel].d.u;
      window.location.href = st.gehe;
    }
  }, true);
  // Ceres sucht beim Loslassen von Enter (keyup) – das unterdruecken, wenn wir schon einen Artikel oeffnen
  ["keyup", "keypress"].forEach(function (typ) {
    document.addEventListener(typ, function (ev) {
      if (st.gehe && ev.key === "Enter" && istSuchfeld(ev.target)) { ev.preventDefault(); ev.stopImmediatePropagation(); }
    }, true);
  });
  document.addEventListener("click", function (ev) {
    if (st.panel && st.panel.style.display === "block" && !st.panel.contains(ev.target) && !istSuchfeld(ev.target)) schliessen();
  }, true);

  window.OSVSuche = { laden: laden, suchen: function (q) { return suchen(q); }, version: "0.4.3" };
})();
