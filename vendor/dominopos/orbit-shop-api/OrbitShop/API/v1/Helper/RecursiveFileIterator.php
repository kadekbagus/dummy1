<?php namespace OrbitShop\API\v1\Helper;
/**
 * Get list of files and directories recursively.
 *
 * @credit http://stackoverflow.com/questions/2930405/sort-directory-listing-using-recursivedirectoryiterator
 * @author Rio Astamal <me@rioastamal.net>
 */
use \SplHeap;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;

class RecursiveFileIterator extends SplHeap
{
    /**
     * Base path for the recursive traversal
     *
     * @var string
     */
    public $path = '/';

    /**
     * Constructor
     *
     * @param string $path Path of the files
     */
    public function __construct($path)
    {
        $this->path = $path;
        $dirIterator = new RecursiveDirectoryIterator($this->path);
        $iterator = new RecursiveIteratorIterator($dirIterator);

        while ($iterator->valid())
        {
            if ($iterator->isDot() === FALSE)
            {
                {
                    $this->insert($iterator->key());
                }
            }
            $iterator->next();
        }
    }

    /**
     * Static function to instantiate the object.
     *
     * @author Rio Astamal <rio@wowrack.com>
     * @param string $path - Path of the files
     * @return RecursiveFileIterator
     */
    public static function create($path)
    {
        return new static($path);
    }

    /**
     * Metho to comapre each node to determine the order.
     *
     * @param object $item1 Iterator object one
     * @param object $item2 Iterator obejct two
     * @return int
     */
    public function compare($item1, $item2)
    {
        return strcmp($item2, $item1);
    }
}
