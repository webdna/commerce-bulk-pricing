<?php

namespace webdna\commerce\bulkpricing\integrations\feedme;

use Cake\Utility\Hash;
use craft\feedme\base\Field;
use craft\feedme\base\FieldInterface;
use craft\feedme\helpers\DataHelper;

use craft\feedme\Plugin as FeedMe;

class BulkPricingField extends Field implements FieldInterface
{
    /**
     * @var string
     */
    public static $name = 'BulkPricingField';

    /**
     * @var string
     */
    public static $class = \webdna\commerce\bulkpricing\fields\BulkPricingField::class;


  /**
   * @inheritDoc
   */
    public function getMappingTemplate(): string {
        return 'commerce-bulk-pricing/_feedme';
    }

    /**
     * @inheritDoc
     */
    public function parseField(): mixed
    {
        FeedMe::info('Parsing bulk pricing field: '. Hash::get($this->fieldInfo, 'node'));
        $parsedData = [];

        $columns = Hash::get($this->fieldInfo, 'fields');

        if (!$columns) {
            return null;
        }

        foreach ($this->feedData as $nodePath => $value) {
            foreach ($columns as $columnHandle => $columnInfo) {

                // Strip out array numbers in the feed path like: MatrixBlock/0/Images/0. We use this to get the field
                // it's supposed to match up with, which is stored in the DB like MatrixBlock/Images
                $feedPath = preg_replace('/(\/\d+\/)/', '/', $nodePath);
                $feedPath = preg_replace('/^(\d+\/)|(\/\d+)/', '', $feedPath);

                $node = Hash::get($columnInfo, 'node');
                $qty = Hash::get($columnInfo, 'qty');
                $iso = Hash::get($columnInfo, 'iso');

                if (!isset($parsedData[$iso])) {
                    $parsedData[$iso] = [];
                    $parsedData[$iso]["iso"] = $iso;
                }

                if ($feedPath == $node || $nodePath == $node) {
                    $parsedData[$iso][$qty] = $value;
                }
            }
        }

        return $parsedData;
  }
}
