<?php
namespace Boxalino\Exporter\Service\Util;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;

class FileHandler
{

    /**
     * @var string
     */
    public $XML_DELIMITER = ',';

    /**
     * @var string
     */
    public $XML_ENCLOSURE = '"';

    /**
     * @var string
     */
    protected $_mainDir;

    /**
     * @var string
     */
    protected $account;
    protected $_dir;
    protected $type;
    protected $_files = [];
    protected $filesMtM = [];

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @param $filesystem
     */
    public function __construct(
        Filesystem $filesystem
    ) {
        $this->filesystem = $filesystem;
    }

    /**
     * Prepares rootr directory where the exported files are to be stored
     */
    public function init() : void
    {
        /** @var \Magento\Framework\Filesystem\Directory\Write $directory */
        $directory = $this->filesystem->getDirectoryWrite(
            DirectoryList::TMP
        );
        $directory->create();

        $this->_mainDir = $directory->getAbsolutePath() . "boxalino";
        if (!file_exists($this->_mainDir)) {
            mkdir($this->_mainDir);
        }

        $this->_dir = $this->_mainDir . '/' . $this->account;
        if (file_exists($this->_dir)) {
            $this->delTree($this->_dir);
        }
    }

    /**
     * @param string $dir
     * @return bool|void
     */
    public function delTree(string $dir) : ?bool
    {
        if (!file_exists($dir)) {
            return null;
        }
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            if (is_dir("$dir/$file")) {
                self::delTree("$dir/$file");
            } else if (file_exists("$dir/$file")) {
                @unlink("$dir/$file");
            }
        }
        return rmdir($dir);
    }

    /**
     * @param string $file
     * @param array $data
     */
    public function savePartToCsv(string $file, array &$data) : void
    {
        $path = $this->getPath($file);
        $fh = fopen($path, 'a');
        foreach($data as $dataRow){
            fputcsv($fh, $dataRow, $this->XML_DELIMITER, $this->XML_ENCLOSURE);
        }

        fclose($fh);
        $data = null;
    }

    /**
     * @param string $file
     * @return string
     */
    public function getFileContents(string $file) : string
    {
        return file_get_contents($this->getPath($file));
    }

    /**
     * @param $file
     * @return string
     */
    public function getPath($file) {

        if (!file_exists($this->_dir)) {
            mkdir($this->_dir);
        }

        //save
        if (!in_array($file, $this->_files)) {
            $this->_files[] = $file;
        }

        return $this->_dir . '/' . $file;
    }

    /**
     * @param $files
     */
    public function prepareProductFiles($files)
    {
        foreach ($files as $attrs) {
            foreach($attrs as $attr){
                $key = $attr['attribute_code'];

                if ($attr['attribute_code'] == 'categories') {
                    $key = 'category';
                }

                if (!file_exists($this->_dir)) {
                    mkdir($this->_dir);
                }
                $file = 'product_' . $attr['attribute_code'] . '.csv';

                //save
                if (!in_array($file, $this->_files)) {
                    $this->_files[] = $file;
                }

                $fh = fopen($this->_dir . '/' . $file, 'a');
                $this->filesMtM[$attr['attribute_code']] = $fh;
            }
        }
    }

    /**
     * removing empty files from the exporter path
     *
     * @param null $fileNamePattern
     */
    public function clearEmptyFiles($fileNamePattern = null)
    {
        $files = array_diff(scandir($this->_dir), ['..','.']);
        foreach ($files as $file)
        {
            $filePath = $this->_dir . "/" . $file;
            if(filesize($filePath))
            {
                continue;
            }

            if(!is_null($fileNamePattern) && (substr($file, 0, strlen($fileNamePattern)) === $fileNamePattern))
            {
                @unlink($filePath);
                continue;
            }

            @unlink($filePath);
        }
    }

    /**
     * @param $account
     * @return FileHandler
     */
    public function setAccount(string $account) : self
    {
        $this->account = $account;
        return $this;
    }

    /**
     * @param string $type
     * @return FileHandler
     */
    public function setType(string $type) : self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param string $dirPath
     * @return FileHandler
     */
    public function setMainDir(string $dirPath) : self
    {
        $this->_mainDir = $dirPath;
        return $this;
    }

}
