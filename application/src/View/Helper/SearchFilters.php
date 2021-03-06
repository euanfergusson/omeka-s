<?php
namespace Omeka\View\Helper;

use Omeka\Api\Exception\NotFoundException;
use Zend\View\Helper\AbstractHelper;

class SearchFilters extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/search-filters';

    /**
     * Render filters from search query.
     *
     * @return array
     */
    public function __invoke($partialName = null)
    {
        $partialName = $partialName ?: self::PARTIAL_NAME;

        $translate = $this->getView()->plugin('translate');

        $filters = [];
        $api = $this->getView()->api();
        $query = $this->getView()->params()->fromQuery();
        $queryTypes = [
            'eq' => $translate('is exactly'),
            'neq' => $translate('is not exactly'),
            'in' => $translate('contains'),
            'nin' => $translate('does not contain'),
            'res' => $translate('has resource'),
            'nres' => $translate('does not have resource'),
            'ex' => $translate('has any value'),
            'nex' => $translate('has no values'),
        ];

        foreach ($query as $key => $value) {
            if ($value != null) {
                switch ($key) {
                    // Search by class
                    case 'resource_class_id':
                        $filterLabel = $translate('Resource class');
                        try {
                            $filterValue = $api->read('resource_classes', $value)->getContent()->label();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown');
                        }
                        $filters[$filterLabel][] = $filterValue;
                        break;

                    // Search values (by property or all)
                    case 'property':
                        $index = 0;
                        foreach ($value as $queryRow) {
                            if (!(is_array($queryRow)
                                && array_key_exists('property', $queryRow)
                                && array_key_exists('type', $queryRow)
                            )) {
                                continue;
                            }
                            $propertyId = $queryRow['property'];
                            $queryType = $queryRow['type'];
                            $joiner = isset($queryRow['joiner']) ? $queryRow['joiner'] : null;
                            $value = isset($queryRow['text']) ? $queryRow['text'] : null;

                            if (!$value && $queryType !== 'nex' && $queryType !== 'ex') {
                                continue;
                            }
                            if ($propertyId) {
                                try {
                                    $propertyLabel = $api->read('properties', $propertyId)->getContent()->label();
                                } catch (NotFoundException $e) {
                                    $propertyLabel = $translate('Unknown property');
                                }
                            } else {
                                $propertyLabel = $translate('[Any property]');
                            }
                            if (!isset($queryTypes[$queryType])) {
                                continue;
                            }
                            $filterLabel = $propertyLabel . ' ' . $queryTypes[$queryType];
                            if ($index > 0) {
                                if ($joiner === 'or') {
                                    $filterLabel = $translate('OR') . ' ' . $filterLabel;
                                } else {
                                    $filterLabel = $translate('AND') . ' ' . $filterLabel;
                                }
                            }

                            $filters[$filterLabel][] = $value;
                            $index++;
                        }
                        break;
                    case 'search':
                        $filterLabel = $translate('Search');
                        $filters[$filterLabel][] = $value;
                        break;

                    // Search resource template
                    case 'resource_template_id':
                            $filterLabel = $translate('Resource Template');
                            try {
                                $filterValue = $api->read('resource_templates', $value)->getContent()->label();
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown resource template');
                            }
                            $filters[$filterLabel][] = $filterValue;
                        break;

                    // Search item set
                    case 'item_set_id':
                        if (!is_array($value)) {
                            $value = [$value];
                        }
                        foreach ($value as $subValue) {
                            if (!is_numeric($subValue)) {
                                continue;
                            }
                            $filterLabel = $translate('Item Set');
                            try {
                                $filterValue = $api->read('item_sets', $subValue)->getContent()->displayTitle();
                            } catch (NotFoundException $e) {
                                $filterValue = $translate('Unknown item set');
                            }
                            $filters[$filterLabel][] = $filterValue;
                        }
                        break;

                    // Search user
                    case 'owner_id':
                        $filterLabel = $translate('User');
                        try {
                            $filterValue = $api->read('users', $value)->getContent()->name();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown user');
                        }
                        $filters[$filterLabel][] = $filterValue;
                        break;

                    case 'site_id':
                        $filterLabel = $translate('Site');
                        try {
                            $filterValue = $api->read('sites', $value)->getContent()->title();
                        } catch (NotFoundException $e) {
                            $filterValue = $translate('Unknown site');
                        }
                        $filters[$filterLabel][] = $filterValue;
                        break;
                }
            }
        }

        $result = $this->getView()->trigger(
            'view.search.filters',
            ['filters' => $filters, 'query' => $query],
            true
        );
        $filters = $result['filters'];

        return $this->getView()->partial(
            $partialName,
            [
                'filters' => $filters,
            ]
        );
    }
}
