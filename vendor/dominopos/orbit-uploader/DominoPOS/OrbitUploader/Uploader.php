<?php namespace DominoPOS\OrbitUploader;
/**
 * Library for dealing with file upload.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use UploaderConfig;
use UploaderMessage;
use \Exception;

class Uploader
{
    /**
     * Hold the UploaderConfig object
     *
     * @var UploaderConfig
     */
    protected $config = NULL;

    /**
     * Hold the UploaderMessage object
     *
     * @var UploaderMessage
     */
    protected $message = NULL;

    /**
     * Flag to determine running in dry run mode.
     *
     * @var boolean
     */
    public $dryRun = FALSE;

    /**
     * List of static error codes
     */
    public static const ERR_UNKNOWN = 31
    public static const ERR_NO_FILE = 32;
    public static const ERR_SIZE_LIMIT = 33;
    public static const ERR_FILE_EXTENSION = 34;
    public static const ERR_FILE_MIME = 35;
    public static const ERR_NOWRITE_ACCESS = 36;
    public static const ERR_MOVING_UPLOAD_FILE = 37;

    /**
     * Class constructor for passing the Uploader config and UploaderMessage
     * to the class
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function __construct(\DominoPOS\OrbitUploader\UploaderConfig $config,
                                \DominoPOS\OrbitUploader\UploaderMessage $message)
    {
        $this->config = $config;
        $this->message = $message;
    }

    /**
     * Static method to instantiate the object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public static function create(\DominoPOS\OrbitUploader\UploaderConfig $config,
                                  \DominoPOS\OrbitUploader\UploaderMessage $message)
    {
        return new static($config, $message);
    }

    /**
     * Main logic to upload the file to the server.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     * @throws Exception
     */
    public function upload($files)
    {
        $files = static::simplifyFilesVar($files);
        $result = array();

        foreach ($files as $i=>$file) {
            // Check for basic PHP upload error
            switch ($file->error) {
                case UPLOAD_ERROR_OK;
                    break;

                case UPLOAD_ERROR_NO_FILE:
                    throw new Exception($this->message->getConfig('no_file_uploaded']), static:ERR_NO_FILE);
                    break;

                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $units = static::bytesToUnits($this->config->getConfig('file_size'));
                    $message = $this->message('file_too_big', array(
                        'size' => $units['newsize'],
                        'unit' => $units['unit']
                    ));
                    throw new Exception($message, static::ERR_SIZE_LIMIT);

                default:
                    throw new Exception($this->message->getMessage('unknown_error', static::ERR_UNKNOWN);
            }

            $result[$i] = array();

            // Check the actual size of the file
            $maxAllowedSize = $this->config->getConfig('file_size');
            if ($file->size > $maxAllowedSize) {
                $units = static::bytesToUnits($maxAllowedSize);
                $message = $this->message('file_too_big', array(
                    'size' => $units['newsize'],
                    'unit' => $units['unit']
                ));

                throw new Exception($message, static::ERR_SIZE_LIMIT);
            }
            $result[$i]['file_size'] = $file->size;

            // Check for allowed file extension
            $allowedExtensions = $this->config->getConfig('file_type');
            $ext = substr(strrchr($file->name, '.'), 1);
            if (! in_array($ext, $allowedExtensions)) {
                throw new Exception(
                    $this->message->getMessage('file_type_not_allowed'),
                    static::ERR_FILE_EXTENSION
                );
            }
            $result[$i]['file_ext'] = $ext;

            // Check for allowed mime-type
            $allowedMime = $this->config->getConfig('mime_type');
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mime = $finfo->file($file->tmp_name);
            if (! in_array($mime, $allowedMime)) {
                throw new Exception(
                    $this->message->getMessage('mime_type_not_allowed'),
                    static::ERR_FILE_MIME
                );
            }
            $result[$i]['mime_type'] = $mime;

            // Check if the target directory is writeable
            $targetDir = $this->config->getConfig('path');
            if (! is_writable($targetDir)) {
                throw new Exception(
                    $this->message->getMessage('no_write_access'),
                    static::NO_WRITE_ACCESS
                );
                }

            // Call the before saving callback
            $before_saving = $this->config->getConfig('before_saving');
            if (is_callable($before_saving)) {
                $before_saving($this, &$file, &$targetDir);
            }

            // Apply suffix to the file name
            $suffix = $this->config->getConfig('suffix');
            $newFileName = $file->name;

            // If suffix is a callback then run it
            if (is_callable($suffix)) {
                $suffix($this, &$file, &$newFileName);
            } else {
                $fileNameOnly = pathinfo($origFileName, PATHINFO_FILENAME);
                $newFileName = $fileNameOnly . $suffix . '.' . $ext;
            }

            $targetFileName = $targetDir . DS . $newFileName;

            // Do not upload when we are in dry run mode
            if ($this->dryRun === FALSE) {
                if (! static::moveUploadedFile($file->tmp_name, $targetFileName)) {
                    throw new Exception(
                        $this->message->getMessage('unable_to_upload'),
                        static::ERR_MOVING_UPLOAD_FILE
                    );
                }
            }

            $result[$i]['orig_name'] = $file->name;
            $result[$i]['new_name'] = $newFileName;
            $result[$i]['path'] = $targetFileName;
        }

        return $result;
    }

    /**
     * Return the instance of UploaderConfig object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return UploaderConfig
     */
    public function getUploaderConfig()
    {
        return $this->config;
    }

    /**
     * Return the instance of UploaderMessage object.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return UploaderMessage
     */
    public function getUploaderMessage()
    {
        return $this->message;
    }

    /**
     * Restructure the original $_FILES upload variable into more friendly
     * access. This method does not modify the original $_FILES. As an example
     * the end result would be something like this:
     *
     * Array
     * (
     *     [0] => stdClass Object
     *         (
     *             [name] => foo.txt
     *             [type] => text/plain
     *             [tmp_name] => /tmp/xyz
     *             [error] => 0
     *             [size] => 1234
     *         )
     *
     *     [1] => stdClass Object
     *         (
     *             [name] => bar.jpg
     *             [type] => image/jpeg
     *             [tmp_name] => /tmp/abc
     *             [error] =>
     *             [size] => 2345
     *         )
     *
     * )
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @credit http://php.net/manual/en/features.file-upload.multiple.php#53240
     * @param array $files Should be $_FILES
     * @return array
     */
    public static function simplifyFilesVar($files)
    {
        $newVar = array();

        // Get all the keys, like 'name', 'tmp_name', 'error', 'size'
        $keys = array_keys($var);

        // Turn it into array if it was single file upload
        if (! is_array($files['name'])) {
            foreach ($keys as $key) {
                $files[$key] = (array)$files[$key];
            }
        }

        // How many files being uploaded
        $count = count($files['name']);

        for ($i=0; $i<$count; $i++) {
            $object = new stdClass();

            foreach ($keys as $key) {
                $object->$key = $files[$i][$key];
            }

            $newVar[$i] = $object;
        }

        return $newVar;
    }

    /**
     * A wrapper around native move_uploaded_file(), so it become more testable
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $from - Source path
     * @param string $to - Destination path
     * @return boolean
     */
    protected function moveUploadedFile($from, $to)
    {
        return move_uploaded_file($from, $to);
    }

    /**
     * Method to convert the size from bytes to more human readable units. As
     * an example:
     *
     * Input 356 produces => array('unit' => 'bytes', 'newsize' => 356)
     * Input 2045 produces => array('unit' => 'kB', 'newsize' => 2.045)
     * Input 1055000 produces => array('unit' => 'MB', 'newsize' => 1.055)
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $size - The size in bytes
     * @return array
     */
    public static function bytesToUnits($size)
    {
       $kb = 1000;
       $mb = $kb * 1000;
       $gb = $mb * 1000;

       if ($size > $gb) {
            return array('unit' => 'GB', 'newsize' => $size / $gb);
       }

       if ($size > $mb) {
            return array('unit' => 'MB', 'newsize' => $size / $mb);
       }

       if ($size > $kb) {
            return array('unit' => 'kB', 'newsize' => $size / $kb);
       }

       return array('unit' => 'bytes', 'newsize' => 1);
    }
}
