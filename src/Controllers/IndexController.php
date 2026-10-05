<?php

namespace OSVSuche\Controllers;

use Plenty\Modules\Webshop\ItemSearch\SearchPresets\VariationList;
use Plenty\Modules\Webshop\ItemSearch\Services\ItemSearchService;
use Plenty\Plugin\CachingRepository;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;

/**
 * Stufe 1: Suchindex aller im Shop sichtbaren Varianten.
 * Die Webshop-Suche von Plenty wendet Sichtbarkeit, Mandant und Sprache selbst an,
 * der Index enthaelt also nur, was ein Kunde auch sehen kann.
 */
class IndexController extends Controller
{
    const CACHE_KEY      = 'osvsuche_index_v1';
    const CACHE_MINUTES  = 60;
    const PAGE_SIZE      = 100;
    const MAX_PAGES      = 80; // Sicherung: hoechstens 8.000 Varianten

    public function index(Request $request, CachingRepository $cache)
    {
        /** @var ItemSearchService $searchService */
        $searchService = pluginApp(ItemSearchService::class);

        $refresh = $request->get('refresh', '') === '1';
        if (!$refresh) {
            $cached = $cache->get(self::CACHE_KEY);
            if (is_array($cached)) {
                $cached['_meta']['ausCache'] = true;
                return $cached;
            }
        }

        $start = microtime(true);
        $docs = [];
        $pageTimes = [];
        $total = 0;
        $page = 1;

        do {
            $t0 = microtime(true);
            $factory = VariationList::getSearchFactory(['sorting' => 'variation.id_asc']);
            $factory->setPage($page, self::PAGE_SIZE);
            $result = $searchService->getResult($factory);
            $total = (int)($result['total'] ?? 0);
            foreach (($result['documents'] ?? []) as $document) {
                $docs[] = $this->toDoc($document['data'] ?? []);
            }
            $pageTimes[] = (int)round((microtime(true) - $t0) * 1000);
            $page++;
        } while (count($docs) < $total && $page <= self::MAX_PAGES && !empty($result['documents']));

        $data = [
            '_meta' => [
                'erzeugt'       => date('c'),
                'anzahl'        => count($docs),
                'gesamtLautPlenty' => $total,
                'seiten'        => count($pageTimes),
                'dauerMs'       => (int)round((microtime(true) - $start) * 1000),
                'seitenMs'      => $pageTimes,
                'ausCache'      => false,
            ],
            'docs' => $docs,
        ];

        $cache->put(self::CACHE_KEY, $data, self::CACHE_MINUTES);
        return $data;
    }

    private function toDoc(array $d): array
    {
        $itemId = $d['item']['id'] ?? 0;
        $variationId = $d['variation']['id'] ?? 0;
        $urlPath = $d['texts']['urlPath'] ?? '';

        $attributes = [];
        foreach (($d['attributes'] ?? []) as $attribute) {
            $value = $attribute['value']['names']['name'] ?? '';
            if ($value !== '') {
                $attributes[] = $value;
            }
        }

        $image = $d['images']['variation'][0]['urlPreview'] ?? ($d['images']['all'][0]['urlPreview'] ?? '');

        return [
            'id'  => $variationId,
            'i'   => $itemId,
            'n'   => $d['texts']['name1'] ?? '',
            'v'   => $d['variation']['name'] ?? '',
            'a'   => implode(' ', $attributes),
            'nr'  => $d['variation']['number'] ?? '',
            'h'   => $d['item']['manufacturer']['externalName'] ?? ($d['item']['manufacturer']['name'] ?? ''),
            'k'   => $d['defaultCategories'][0]['id'] ?? 0,
            'u'   => '/' . $urlPath . '_' . $itemId . '_' . $variationId . '/',
            'b'   => $image,
            'p'   => $d['prices']['default']['price']['formatted'] ?? '',
            'm'   => !empty($d['variation']['isMain']),
            'ok'  => ($d['variation']['availability']['mappedAvailability'] ?? '') === 'https://schema.org/InStock',
        ];
    }
}
