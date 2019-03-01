<?php
/**
 * Commerce Bulk Pricing plugin for Craft CMS 3.x
 *
 * Bulk pricing for products
 *
 * @link      https://kurious.agency
 * @copyright Copyright (c) 2019 Kurious Agency
 */

namespace kuriousagency\commerce\bulkpricing\fields;

use kuriousagency\commerce\bulkpricing\BulkPricing;
use kuriousagency\commerce\bulkpricing\assetbundles\bulkpricingfield\BulkPricingFieldAsset;

use craft\commerce\Plugin as Commerce;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\fields\data\ColorData;
use craft\helpers\DateTimeHelper;
use craft\helpers\Json;
use craft\validators\ColorValidator;
use craft\web\assets\tablesettings\TableSettingsAsset;
use craft\web\assets\timepicker\TimepickerAsset;
use yii\db\Schema;

/**
 * @author    Kurious Agency
 * @package   CommerceBulkPricing
 * @since     1.0.0
 */
class BulkPricingField extends Field
{
    // Public Properties
    // =========================================================================

    // Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce-bulk-pricing', 'Bulk Pricing');
	}
	
	public $columns = [
        'col1' => [
            'heading' => '',
            'qty' => '',
        ]
	];
	
	public $columnType = Schema::TYPE_TEXT;

	public $userGroups;

	public $taxIncluded;

    // Public Methods
	// =========================================================================

	public function init()
    {
        parent::init();

        if (!is_array($this->columns)) {
            $this->columns = [];
        }

    }
	
	

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getContentColumnType(): string
    {
        return $this->columnType;
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {
        if (is_string($value) && !empty($value)) {
            $value = Json::decodeIfJson($value);
		}

        if (!is_array($value) || empty($this->columns)) {
            return null;
		}

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ElementInterface $element = null)
    {
        if (!is_array($value) || empty($this->columns)) {
            return null;
		}
		return $value;
		Craft::dump('serialize');
		Craft::dd($value);

        $serialized = [];

        foreach ($value as $row) {
            $serializedRow = [];
            foreach (array_keys($this->columns) as $colId) {
                $serializedRow[$colId] = parent::serializeValue($row[$colId] ?? null);
            }
            $serialized[] = $serializedRow;
		}
		
		Craft::dd($serialized);

        return $serialized;
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        $columnSettings = [
            'heading' => [
                'heading' => Craft::t('app', 'Column Heading'),
                'type' => 'singleline',
                'autopopulate' => 'handle'
            ],
            'qty' => [
                'heading' => Craft::t('app', 'Qty'),
                'code' => true,
                'type' => 'number'
            ],
		];
		
        $view = Craft::$app->getView();

        $view->registerJs('new Craft.TableFieldSettings(' .
            Json::encode($view->namespaceInputName('columns'), JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($view->namespaceInputName('defaults'), JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($this->columns, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode([], JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($columnSettings, JSON_UNESCAPED_UNICODE) .
            ');');

        $columnsField = $view->renderTemplateMacro('_includes/forms', 'editableTableField', [
            [
                'label' => Craft::t('app', 'Table Columns'),
                'instructions' => Craft::t('app', 'Define the columns your table should have.'),
                'id' => 'columns',
                'name' => 'columns',
                'cols' => $columnSettings,
                'rows' => $this->columns,
                'addRowLabel' => Craft::t('app', 'Add a column'),
                'initJs' => false
            ]
		]);

        return $view->renderTemplate('commerce-bulk-pricing/_components/fields/BulkPricingField_settings', [
            'field' => $this,
			'columnsField' => $columnsField,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {

        // Get our id and namespace
        $id = Craft::$app->getView()->formatInputId($this->handle);
        $namespacedId = Craft::$app->getView()->namespaceInputId($id);

        // Variables to pass down to our field JavaScript to let it namespace properly
        $jsonVars = [
            'id' => $id,
            'name' => $this->handle,
            'namespace' => $namespacedId,
            'prefix' => Craft::$app->getView()->namespaceInputId(''),
            ];
        $jsonVars = Json::encode($jsonVars);
		
		if (empty($this->columns)) {
            return '';
		}

		$this->columns = array_merge(['col0' => [
			'heading' => 'Currency',
			'qty' => 'iso',
		]], $this->columns);

        // Translate the column headings
        foreach ($this->columns as &$column) {
            if (!empty($column['heading'])) {
                $column['heading'] = Craft::t('site', $column['heading']);
            }
        }
		unset($column);
		
		//Craft::dd($value);

        if (!is_array($value)) {
			$value = [];
			
			if (Craft::$app->plugins->isPluginEnabled('commerce-currency-prices')) {
				foreach (Commerce::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies() as $currency)
				{
					$val = [];
					foreach ($this->columns as $colId => $col) {
						//$val[$colId] = $colId == 'col0' ? $currency->iso : '';
						$val[$col['qty']] = $col['qty'] == 'iso' ? $currency->iso : '';
					}
					$value[] = $val;
				}
			} else {
				$val = [];
				$currency = Commerce::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
				foreach ($this->columns as $colId => $col) {
					//$val[$colId] = $colId == 'col0' ? $currency->iso : '';
					$val[$col['qty']] = $col['qty'] == 'iso' ? $currency->iso : '';
				}
				$value[] = $val;
			}
		}

        // Explicitly set each cell value to an array with a 'value' key
        $checkForErrors = $element && $element->hasErrors($this->handle);
        foreach ($value as &$row) {
            foreach ($this->columns as $colId => $col) {
                if (isset($row[$col['qty']])) {
                    $row[$col['qty']] = [
                        'value' => $row[$col['qty']],
                        'hasErrors' => $checkForErrors,
                    ];
                }
            }
        }
		unset($row);
		
		foreach ($this->columns as &$col) {
			if ($col['qty'] == 'iso') {
				$col['type'] = 'heading';
				$col['heading'] = '';
			} else {
				$col['type'] = 'number';
			}
		}

        $view = Craft::$app->getView();
		$id = $view->formatInputId($this->handle);
		
        return $view->renderTemplate('commerce-bulk-pricing/_components/fields/BulkPricingField_input', [
            'id' => $id,
            'name' => $this->handle,
            'cols' => $this->columns,
            'rows' => $value,
            'minRows' => null,
            'maxRows' => null,
            'static' => false,
            'addRowLabel' => '',
        ]);
	}

	public function getStaticHtml($value, ElementInterface $element): string
	{
		return $this->getInputHtml($value, $element);
	}
	
	public function getElementValidationRules(): array
    {
        return ['validateTableData'];
	}
	
	public function validateTableData(ElementInterface $element)
    {
        /** @var Element $element */
        $value = $element->getFieldValue($this->handle);
	}
	


	// Private Methods
    // =========================================================================


	
}
