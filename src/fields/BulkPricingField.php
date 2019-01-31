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
	
	/*public $defaults = [
        
	];*/
	
	public $columnType = Schema::TYPE_TEXT;

	public $userGroups;

	public $taxIncluded;

	//public $addRowLabel;

    /**
     * @var int|null Maximum number of Rows allowed
     */
    //public $maxRows;

    /**
     * @var int|null Minimum number of Rows allowed
     */
    //public $minRows;

    // Public Methods
	// =========================================================================

	public function init()
    {
        parent::init();

        /*if ($this->addRowLabel === null) {
            $this->addRowLabel = Craft::t('app', 'Add a row');
        }*/

        if (!is_array($this->columns)) {
            $this->columns = [];
        }

        /*if (!is_array($this->defaults)) {
            $this->defaults = [];
        }*/
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
		}/* else if ($value === null && $this->isFresh($element) && is_array($this->defaults)) {
            $value = array_values($this->defaults);
        }*/

        if (!is_array($value) || empty($this->columns)) {
            return null;
		}
		//Craft::dd($value);

        // Normalize the values and make them accessible from both the col IDs and the handles
        /*foreach ($value as &$row) {
            foreach ($this->columns as $colId => $col) {
                $row[$colId] = $row[$colId] ?? null;
                if ($col['qty']) {
                    $row[$col['qty']] = $row[$colId];
                }
            }
        }*/

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
            // 'type' => [
            //     'heading' => Craft::t('app', 'Type'),
            //     'code' => true,
            //     'type' => 'number',
            // ],
            // 'type' => [
            //     'heading' => Craft::t('app', 'Type'),
            //     'class' => 'thin',
            //     'type' => 'select',
            //     'options' => $typeOptions,
            // ],
		];
		
        $view = Craft::$app->getView();

        //$view->registerAssetBundle(TimepickerAsset::class);
		//$view->registerAssetBundle(TableSettingsAsset::class);
		//Craft::dd(Json::encode($view->namespaceInputName('columns'), JSON_UNESCAPED_UNICODE));
        $view->registerJs('new Craft.TableFieldSettings(' .
            Json::encode($view->namespaceInputName('columns'), JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($view->namespaceInputName('defaults'), JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($this->columns, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode([], JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode($columnSettings, JSON_UNESCAPED_UNICODE) .
            ');');

		//{% set input %}{% include "_includes/forms/editableTable" with config only %}{% endset %}
    	//{{ forms.field(config, input) }}
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
		
		// $defaultsField = $view->renderTemplateMacro('_includes/forms', 'editableTableField', [
        //     [
        //         'label' => Craft::t('app', 'Default Values'),
        //         'instructions' => Craft::t('app', 'Define the default values for the field.'),
        //         'id' => 'defaults',
        //         'name' => 'defaults',
        //         'cols' => $this->columns,
        //         'rows' => $this->defaults,
        //         'initJs' => false
        //     ]
        // ]);

        return $view->renderTemplate('commerce-bulk-pricing/_components/fields/BulkPricingField_settings', [
            'field' => $this,
			'columnsField' => $columnsField,
			//'defaultsField' => $defaultsField,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        // Register our asset bundle
        // Craft::$app->getView()->registerAssetBundle(BulkPricingFieldAsset::class);

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
        // Craft::$app->getView()->registerJs("$('#{$namespacedId}-field').BulkPricingField(" . $jsonVars . ");");

        // Render the input template
        /*return Craft::$app->getView()->renderTemplate(
            'commerce-bulk-pricing/_components/fields/BulkPricingField_input',
            [
                'name' => $this->handle,
                'value' => $value,
                'field' => $this,
                'id' => $id,
                'namespacedId' => $namespacedId,
            ]
		);*/
		
		if (empty($this->columns)) {
            return '';
		}

		$this->columns = array_merge(['col0' => [
			'heading' => 'Currency',
			'qty' => 'iso',
		]], $this->columns);

		//Craft::dd($this->columns);

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
		
		//Craft::dd($value);

		/*
0 => [
        'col6' => 'GBP'
        'col1' => '9.10'
        'col2' => '8.75'
        'col3' => '8.60'
        'col4' => '8.30'
        'col5' => '8.10'
        'currency' => 'GBP'
        25 => '9.10'
        50 => '8.75'
        100 => '8.60'
        200 => '8.30'
        500 => '8.10'
    ]
		*/

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

        // Make sure the value contains at least the minimum number of rows
        // if ($this->minRows) {
        //     for ($i = count($value); $i < $this->minRows; $i++) {
        //         $value[] = [];
        //     }
        // }

        $view = Craft::$app->getView();
		$id = $view->formatInputId($this->handle);
		
		//Craft::dd($value);

        return $view->renderTemplate('commerce-bulk-pricing/_components/fields/BulkPricingField_input', [
            'id' => $id,
            'name' => $this->handle,
            'cols' => $this->columns,
            'rows' => $value,
            'minRows' => null,//$this->minRows,
            'maxRows' => null,//$this->maxRows,
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
