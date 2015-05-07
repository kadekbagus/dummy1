<?php namespace Report;
/**
 * Base Intermediate Controller for all controller which need authentication.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use IntermediateAuthBrowserController;
use TenantAPIController;
use View;
use Config;
use Retailer;
use DB;
use PDO;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class DataPrinterController extends IntermediateAuthBrowserController
{
    /**
     * Store the PDO Object
     *
     * @var PDO
     */
    protected $pdo = NULL;


    /**
     * Method to prepare the PDO Object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function preparePDO()
    {
        $prefix = DB::getTablePrefix();
        $default = Config::get('database.default');
        $dbConfig = Config::get('database.connections.' . $default);

        $this->pdo = new PDO("mysql:host=localhost;dbname={$dbConfig['database']}", $dbConfig['username'], $dbConfig['password']);
    }

    /**
     * Method to prepare the unbuffered queries to the MySQL server. It useful
     * because we want to show all the lists and does not want the result
     * to be stored in application memory.
     *
     * The result should be kept on MySQL server and fetched one-by-one using
     * cursor.
     *
     * Call the method preparePDO first.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    protected function prepareUnbufferedQuery()
    {
        $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, FALSE);
    }

    /**
     * Concat the list of collection.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Collection|Array $collection
     * @param string $attribute You want to get
     * @param string $separator Separator for concat result
     * @return String
     */
    public function concatCollection($collection, $attribute, $separator=', ')
    {
        $result = [];

        foreach ($collection as $item) {
            $result[] = $item->{$attribute};
        }

        return implode($separator, $result);
    }
}
