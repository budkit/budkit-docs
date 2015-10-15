<?php

namespace Budkit\Docs;


define('BUDKIT_DOCS_PATH', __DIR__);


use TokenReflection\Broker;

/**
 * Created by PhpStorm.
 * User: livingstonefultang
 * Date: 12/10/15
 * Time: 13:13
 */
class Documentor
{

    public $layout = null;

    protected $broker;

    public $currentPath = [];

    public $saveMode = false;

    public $isIndex = false;

    public $savePath = "/";

    public $saveHierarchy = "";

    public $section = [
//            "title"=>"",
//            "namespace"=>"",
//            "description"=>"",
//            "uses"=>[],
//            "constants"=>[],
//            "methods"=>[],
//            "functions"=>[],
//            "literal"=>[],
    ];

    /**
     * Parse and display the Pocco documentation for the given directory and
     * all contained PHP files. You may also specify the default file to show
     * if none has been requested.
     *
     * @param string $directory
     * @param string $file
     * @return boolean
     */
    public function display($directory, $default = NULL, $requested = NULL)
    {

        $this->broker = $broker = new Broker(new Broker\Backend\Memory());
        //Reflection File
        $rDir = $broker->processDirectory($directory, "*.php", true);

        $files = array_keys($rDir);

        array_walk($files, function (&$value, $key) use ($directory) {
            //echo $directory;
            $value = substr($value, strlen($directory));
        });

        //print_r($files);

        //list($files, $files_array) = $this->getAllPHPFiles($directory);

        if ($requested) {

            if (!in_array($requested, $files)) {
                return false;
            }

            $file = $requested;

        } else {

            if ($default AND ($key = array_search($default, $files))) {
                $file = $files[$key];
            } else {
                $file = $files[0];
            }

        }

        //$source = file_get_contents($directory . $file);
        $sections = $this->parseSource($rDir[$directory . $file], $file, $directory);

        $this->render($sections, $file, $files);

        return true;
    }


    public function processFunctions($functions, &$sections)
    {

        foreach ($functions as $function) {

            $fnc_section = $this->section;

            $labels = [];

            if ($function->isDisabled()) $labels["disabled"] = "error";

            $fnc_section["description"]["doc"]["type"] = "Function";
            $fnc_section["description"]["doc"]["labels"] = $labels;
            $fnc_section["description"]["doc"]["title"] = $function->getShortName();
            $fnc_section["description"]["doc"]["annotations"] = $function->getAnnotations();

            $fnc_section["description"]["code"] = str_ireplace($function->getDocComment(), "", $function->getSource());

            $sections[] = $fnc_section;
        }

    }

    /**
     * Parse the source code of a file into an array of documentation (comments)
     * and source code blocks. Also parse the docblock metadata.
     *
     * @param string $source
     * @return array
     */
    public function parseSource($rFile, $file, $directory)
    {
        $namespace = null;
        $sections = [];
        $section = [];

        foreach ($rFile->getNamespaces() as $namespace) {

            $ns_section = $section;
            $sections[] =& $ns_section;

//            $ns_section["title"]["doc"]["title"]  = $file;
//            $ns_section["title"]["doc"]["type"]  = "file";

            $ns_section["title"]["doc"]["subject"] = $namespace->getName();

            //Description
            //@TODO will need to parse this to extract params;
            if (!empty($namespace->getDocComment())) {

                $ns_section["title"]["doc"]["annotations"] = $namespace->getAnnotations();

                //$ns_section["title"]["doc"]["info"] = $namespace->getDocComment();

            } else if (!empty($rFile->getDocComment())) {

                //We are probably in a non namespaced file, if we have to do this

                $ns_section["title"]["doc"]["subject"] = substr($rFile->getName(), strlen($directory));
                $ns_section["title"]["doc"]["annotations"] = $rFile->getAnnotations();

            }

            //File Functions
            $functions = $namespace->getFunctions();
            if (!empty($functions)) {
                $this->processFunctions($functions, $sections);
            }

            //Class Name
            // print_R($namespace->getAnnotations());

            //run throw classes;
            //start a new section for each class
            $classes = $namespace->getClasses();
            if (!empty($classes)) {
                foreach ($classes as $class) {


                    //Nasty hack to help the broker find class dependencies;
                    //In essence need to give it fully qualified namespaced class
                    //$_class = $class->getName();
                    //$class = $this->broker->getClass("\\".$_class, true);

                    //print_r($__class);

                    $cls_section = $section;
                    $sections[] =& $cls_section;


                    $labels = [];

                    //Description
                    //@TODO will need to parse this to extract params;
                    //$cls_section["description"]["doc"]["type"]  = "class";

                    if ($class->isFinal()) $labels["final"] = "";
                    if ($class->isInterface()) $labels["interface"] = "default";
                    if ($class->isInstantiable()) $labels["class"] = "primary";
                    if ($class->isTrait()) $labels["trait"] = "primary";
                    // if (!$class->isCloneable()) $labels["not clonable"] = "black";
                    if ($class->isException()) $labels["exception"] = "error";
                    if ($class->isAbstract()) $labels["abstract"] = "warning";

                    $cls_section["description"]["doc"]["labels"] = $labels;
                    $cls_section["description"]["doc"]["title"] = $class->getShortName();


                    //get parent classes
                    $interfaces = $class->getInterfaces();
                    $this->processInterfaces($interfaces, $cls_section);

                    if (!empty($class->getDocComment())) {
                        $cls_section["description"]["doc"]["annotations"] = $class->getAnnotations();
                    }


                    $toc = [];

                    //$def_section["description"]["doc"]["type"] = $class->getName();
                    $cls_section["description"]["doc"]["toc"] = ["title" => "Methods", "list" => &$toc];


                    $ptoc = [];

                    //$def_section["description"]["doc"]["type"] = $class->getName();
                    $cls_section["properties"]["doc"]["toc"] = ["title" => "Properties", "list" => &$ptoc];

                    //Properties
                    $properties = $class->getProperties();
                    if (!empty($properties)) {
                        $this->processProperties($properties, $class, $sections, $ptoc, $file);
                    }


                    $methods = $class->getMethods();
                    if (!empty($methods)) {
                        $this->processMethods($methods, $class, $sections, $toc, $file);
                    }

                    //get parent classes
                    $parent = $class->getParentClass();

                    if (!empty($parent)) {

                        // print_r($directory); print("<br />");
                        //print_R($file);
                        //get a reflection of each parent;
                        $this->processInheritance($parent, $sections, $cls_section, $file, $directory);
                    }

                    //Traits
                    $traits = $class->getTraits();

                    if (!empty($traits)) {
                        $this->processTraits($traits, $sections, $cls_section, $file, $directory);
                    }


                    //Constants
                    $constants = $class->getConstants();
                    $this->processConstants($constants, $sections);

                    //If no properties;
                    if (empty($ptoc)) {
                        unset($cls_section["properties"]["doc"]["toc"]);
                    }


                    //If this class has no methods;
                    if (empty($toc)) {
                        unset($cls_section["description"]["doc"]["toc"]);
                    }

                    //If this class has no inherited methods;
                    if (empty($itoc)) {
                        unset($cls_section["inherited"]["doc"]["toc"]);
                    }


                }
            }
            //$sections[] = $section;
        }

        return $sections;
    }

    public function processInterfaces($interfaces, &$cls_section)
    {

        if (!empty($interfaces)) {
            $_interfaces = [];
            foreach ($interfaces as $interface) {

                if ($interface->isInternal()) continue;
                $_interfaces[] = $interface->getName();
            }
            $cls_section["description"]["doc"]["interfaces"] = $_interfaces;
            //print_R($_parents);
            $_interfaces = [];
        }
    }

    public function processConstants($constants, &$sections)
    {

        if (!empty($constants)) {
            $def_section = $this->section;

            $def_section["description"]["doc"]["title"] = "Constants";
            $def_section["description"]["doc"]["annotations"] = $constants;

            $sections[] = $def_section;
        }
    }

    public function processMethods($methods, &$class, &$sections, &$toc, $file, $showdoc = true)
    {

        foreach ($methods as $method) {

            //We only want to deal with methods declared in this class;
            if (!$class->hasOwnMethod($method->getName()) || $method->isPrivate()) continue;

            $mtd_id = "method:" . $method->getShortName();

            $link = (!$class->hasOwnMethod($method->getName()) || !$showdoc)
                ? (($this->saveMode) ?   $this->saveHierarchy.$file.".html"  : "?file=" . $file  ). "#" . $mtd_id
                : "#" . $mtd_id;

            //add the method name to the TOC
            //we don't want to show consructors, private or protected methods here;
            if (!$method->isProtected() && !$method->isPrivate() && $method->getShortName() !== "__construct") {
                $toc[$mtd_id] = ["title" => $method->getShortName() . "()", "link" => $link];
            }

            //Do we want to show the complete doc?
            if (!$showdoc) continue;

            $mtd_section = $this->section;
            $labels = [];

            $mtd_section["description"]["doc"]["type"] = "method";
            $mtd_section["description"]["doc"]["title"] = $method->getShortName();
            $mtd_section["description"]["doc"]["id"] = $mtd_id;

            if ($method->isFinal()) $labels["final"] = "";
            if ($method->isPublic()) $labels["public"] = "success";
            if ($method->isStatic()) $labels["static"] = "default";

            if ($method->isProtected()) $labels["protected"] = "black";
            //if ($method->isPrivate()) $labels["private"] = "error";
            if ($method->isAbstract()) $labels["abstract"] = "warning";

            if ($method->isDeprecated()) $labels["deprecated"] = "error";

            $mtd_section["description"]["doc"]["labels"] = $labels;

            if (!empty($method->getDocComment())) {
                $mtd_section["description"]["doc"]["annotations"] = $method->getAnnotations();
            }

            //$mtd_section["description"]["doc"]["body"] = $method->getDocComment();
            $mtd_section["description"]["code"] = str_ireplace($method->getDocComment(), "", $method->getSource());

            $sections[] = $mtd_section;

        }

    }


    public function processProperties($properties, &$class, &$sections, &$toc, $file, $showdoc = true)
    {
        foreach ($properties as $property) {

            //We only want to deal with methods declared in this class;
            if (!$class->hasOwnProperty($property->getName())) continue;
            if ($property->isPrivate() || $property->isProtected()) continue;

            $pty_id = "property:" . $property->getName();

            $link = (!$class->hasOwnProperty($property->getName()) || !$showdoc)
                ? (($this->saveMode) ?   $this->saveHierarchy.$file.".html"  : "?file=" . $file ). "#" . $pty_id
                : "#" . $pty_id;

            //add the method name to the TOC
            //we don't want to show consructors, private or protected methods here;

            $toc[$pty_id] = ["title" => $property->getName(), "link" => $link];

            //Do we want to show the complete doc?
            if (!$showdoc) continue;

            $pty_section = $this->section;
            $labels = [];


            $pty_section["description"]["doc"]["type"] = "property";
            $pty_section["description"]["doc"]["title"] = $property->getName();
            $pty_section["description"]["doc"]["id"] = $pty_id;

            //if ($property->isFinal()) $labels["final"] = "";
            if ($property->isStatic()) $labels["static"] = "default";
            if ($property->isPublic()) $labels["public"] = "success";

            if ($property->isProtected()) $labels["protected"] = "black";
            //if ($property->isPrivate()) $labels["private"] = "error";

            //if ($property->isAbstract()) $labels["abstract"] = "warning";

            if ($property->isDeprecated()) $labels["deprecated"] = "error";

            $pty_section["description"]["doc"]["labels"] = $labels;

            if (!empty($property->getDocComment())) {
                $pty_section["description"]["doc"]["annotations"] = $property->getAnnotations();
            }

            //$mtd_section["description"]["doc"]["body"] = $method->getDocComment();
            $pty_section["description"]["code"] = str_ireplace($property->getDocComment(), "", $property->getSource());

            $sections[] = $pty_section;

        }

    }

    public function processInheritance($parent, &$sections, &$cls_section, $file, $directory, $itoc = [])
    {
        //$parentTree = [];
        if (!empty($parent)) {

            $cls_section["description"]["doc"]["parents"][] = $parent->getName();

            $_toc = [];
            $_ptoc = [];

            $file = substr($parent->getFileName(), strlen($directory));

            $itd_section = $this->section;
            $itd_section["description"]["doc"]["toc"] = ["title" => "Inherited Methods from {$parent->getName()}", "list" => &$_toc];


            $itd_section["properties"]["doc"]["toc"] = ["title" => "Inherited Properties from {$parent->getName()}", "list" => &$_ptoc];

            $methods = $parent->getMethods();
            if (!empty($methods)) {
                $this->processMethods($methods, $parent, $sections, $_toc, $file, false);
            }

            $properties = $parent->getProperties();
            if (!empty($properties)) {
                $this->processProperties($properties, $parent, $sections, $_ptoc, $file, false);

                if (empty($_ptoc)) {
                    unset($itd_section["properties"]);
                }
            }

            if (!empty($_toc)) {
                $sections[] = $itd_section;
            }


            $parents_parent = $parent->getParentClass();

            if (!empty($parents_parent)) {

                //Loop through all the parents parents, until no more parents
                $this->processInheritance($parents_parent, $sections, $cls_section, $file, $directory, $itoc);

            }
        }
    }


    public function processTraits($traits, &$sections, &$cls_section, $file, $directory, $itoc = [])
    {
        if (!empty($traits)) {

            foreach ($traits as $parent) {

                $file = substr($parent->getFileName(), strlen($directory));

                $cls_section["description"]["doc"]["traits"][] = $parent->getName();

                $_toc = [];
                $_ptoc = [];

                $itd_section = $this->section;
                $itd_section["description"]["doc"]["toc"] = ["title" => "Inherited Methods from {$parent->getName()}", "list" => &$_toc];


                $itd_section["properties"]["doc"]["toc"] = ["title" => "Inherited Properties from {$parent->getName()}", "list" => &$_ptoc];

                $methods = $parent->getMethods();
                if (!empty($methods)) {
                    $this->processMethods($methods, $parent, $sections, $_toc, $file, false);
                }

                $properties = $parent->getProperties();
                if (!empty($properties)) {
                    $this->processProperties($properties, $parent, $sections, $_ptoc, $file, false);

                    if (empty($_ptoc)) {
                        unset($itd_section["properties"]);
                    }
                }

                if (!empty($_toc)) {
                    $sections[] = $itd_section;
                }
            }
        }
    }


    /**
     * Parse docblock parameters extracted from the end of the docblock
     *
     * @param string $params
     * @return array
     */
    public function parseParams($params)
    {
        $params = trim($params);

        $lines = explode("\n", $params);

        $list = array('params' => array(), 'return' => '', 'other' => array());

        $other = '';

        foreach ($lines as $line) {

            $line = substr($line, 1);

            // Replace all tabs with spaces for the code below
            $line = preg_replace('~\s+~', ' ', $line);

            $line = explode(' ', $line, 3) + array('', '', '');

            if ($line[0] == 'param') {

                $list['params'][] = array(
                    'type' => $line[0],
                    'name' => $line[1],
                    'desc' => $line[2],
                );

            } elseif ($line[0] == 'return') {

                if ($line[1] !== 'void') {
                    $list['params'][] = array(
                        'type' => 'returns',
                        'name' => $line[1],
                        'desc' => '',
                    );
                }

            } elseif (in_array($line[0], array('var', 'category', 'package', 'subpackage'))) {

                // Ignore these since they don't add much to the documentation

            } else {

                $line = join(' ', $line);
                //$line = preg_replace('~\w+://\S+~', '<a href="$0">$0</a>', $line);
                $list['other'][] = $line;
            }

        }

        return $list;
    }

    /**
     * An array of known docblock tags
     *
     * @return array
     */
    public function doblockTags()
    {
        return array(
            'abstract', 'access', 'author', 'category', 'copyright', 'deprecated',
            'example', 'final', 'filesource', 'global', 'ignore', 'internal',
            'license', 'link', 'method', 'name', 'package', 'param', 'property',
            'return', 'see', 'since', 'static', 'staticvar', 'subpackage', 'todo',
            'tutorial', 'uses', 'var', 'version'
        );
    }

    /**
     * Recursively scan the given directory and all sub-folders for PHP files and
     * return two arrays. The first array is the full relative file path, the second
     * is a nested associative array mimicking the file system.
     */
    public function getAllPHPFiles($dir)
    {
        $flags = \FilesystemIterator::KEY_AS_PATHNAME
            | \FilesystemIterator::CURRENT_AS_FILEINFO
            | \FilesystemIterator::SKIP_DOTS
            | \FilesystemIterator::UNIX_PATHS;

        $ritit = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, $flags),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        $files = array();
        $r = array();
        foreach ($ritit as $key => $splFileInfo) {

            if ($splFileInfo->getExtension() !== 'php') {
                continue;
            }

            if ($splFileInfo->isFile()) {
                $files[] = substr($key, strlen($dir));
            }

            $path = $splFileInfo->isDir()
                ? array($splFileInfo->getFilename() => array())
                : array($splFileInfo->getFilename());

            for ($depth = $ritit->getDepth() - 1; $depth >= 0; $depth--) {
                $path = array($ritit->getSubIterator($depth)->current()->getFilename() => $path);
            }

            $r = array_merge_recursive($r, $path);
        }

        return array($files, $r);
    }

    /**
     * Render the documentation HTML for the given sections and files
     *
     * @param array $sections from parsed PHP file
     * @param string $file the filename
     * @param array $files the array of other project files
     * @return void
     */
    public function render($sections, $file, $files)
    {
        //sort files into namespaces
        $namespaced = [];

        foreach ($files as $key => $namespace) {
            $namespaced[$namespace] = $namespace;
        }

        $tree = $this->explodeTree($namespaced, "/", true);
        /*
        * // Show //
         *
         */
        //print_r($tree);

        if (!$this->layout) {
            $this->layout = __DIR__ . '/Layout.php';
        }

        require($this->layout);

    }


    /**
     * Parse and display the Pocco documentation for the given directory and
     * all contained PHP files. You may also specify the default file to show
     * if none has been requested.
     *
     * @param string $directory
     * @param string $file
     * @return boolean
     */
    public function saveHTML($directory, $default = NULL, $requested = NULL)
    {

        $this->broker = $broker = new Broker(new Broker\Backend\Memory());
        //Reflection File
        $rDir = $broker->processDirectory($directory, "*.php", true);

        $files = array_keys($rDir);

        array_walk($files, function (&$value, $key) use ($directory) {
            //echo $directory;
            $value = substr($value, strlen($directory));
        });

        //sort files into namespaces
        $this->saveMode = true;
        $this->savePath = dirname($directory) . "/docs";

        foreach ($files as $file) {


            //Wee need this to fix links
            if(!$this->isIndex) {

                $_segments = explode("/", $file);
                $_levels = count($_segments);

                //if not saving index

                for ($_i = 0; $_i < $_levels - 1; $_i++) {
                    $this->saveHierarchy .= "../";
                }
            }

            //$source = file_get_contents($directory . $file);
            $sections = $this->parseSource($rDir[$directory . $file], $file, $directory);

            $this->renderSave($sections, $file, $files, dirname($directory) . "/docs");

            //reset this heierarchy
            $this->saveHierarchy = "";

        }

        //create the index file which should be the first in files;
        $this->isIndex = true;
        $this->renderSave(
            //get the first files sections
            $this->parseSource($rDir[$directory . $files[0]], $files[0], $directory),
            $file, $files, $this->savePath, "index.html"

        );

        //copy assets into docs directory;
        $this->xcopy(BUDKIT_DOCS_PATH."/assets", $this->savePath."/assets", 0777);


        return true;
    }


    /**
     * Copy a file, or recursively copy a folder and its contents
     * @author      Aidan Lister <aidan@php.net>
     * @version     1.0.1
     * @link        http://aidanlister.com/2004/04/recursively-copying-directories-in-php/
     * @param       string   $source    Source path
     * @param       string   $dest      Destination path
     * @param       string   $permissions New folder creation permissions
     * @return      bool     Returns true on success, false on failure
     */
    public function xcopy($source, $dest, $permissions = 0755)
    {
        // Check for symlinks
        if (is_link($source)) {
            return symlink(readlink($source), $dest);
        }

        // Simple copy for a file
        if (is_file($source)) {
            return copy($source, $dest);
        }

        // Make destination directory
        if (!is_dir($dest)) {
            mkdir($dest, $permissions);
        }

        // Loop through the folder
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Deep copy directories
            $this->xcopy("$source/$entry", "$dest/$entry", $permissions);
        }

        // Clean up
        $dir->close();
        return true;
    }

    /**
     * Render the documentation HTML for the given sections and files
     *
     * @param array $sections from parsed PHP file
     * @param string $file the filename
     * @param array $files the array of other project files
     * @return void
     */
    private function renderSave($sections, $file, $files, $savePath, $saveAs = null)
    {

        $namespaced = [];

        foreach ($files as $key => $namespace) {
            $namespaced[$namespace] = $namespace;
        }

        $tree = $this->explodeTree($namespaced, "/", true);



        if (!$this->layout) {
            $this->layout = __DIR__ . '/Layout.php';
        }

        ob_start();

        require($this->layout);

        $page = ob_get_contents();
        ob_end_clean();

        $saveFile = $savePath . "/" . $file . ".html";
        $dirname = dirname($saveFile);

        if (!is_dir($dirname)) {
            if (mkdir($dirname, 0777, true)) {

            } else {
                echo "Error: Could not create {$saveFile}" . "<br />";
                return;
            }
        }

        //Save the file;

        $savedFile = (!$saveAs) ? $saveFile : $savePath . "/" . $saveAs;
        $fw = fopen($savedFile, "w");
        fputs($fw, $page, strlen($page));
        fclose($fw);

        @chmod($savedFile, 0777);

        return true;

    }


    function listTree($array = [], $file)
    {

        $level = 1;
        $ol = "<ol class='tree'>";

        foreach ($array as $key => $value) {

            if (!is_array($value)) {

                $this->currentPath[] = $value;
                $link = (!$this->saveMode)? rawurlencode($value) : $value;
                $href = ($this->saveMode) ? $this->saveHierarchy . $link.".html"  : "?file=" . $link;
                $li = '<li class="file">';
                $li .= '<a href="'.$href.'">' . $key . '</a>';
                $li .= '</li>';

                //$path   = "";
                array_pop($this->currentPath);

                $ol .= $li;

            } else {
                //$path .= "/".$key;
                $this->currentPath[] = $key;

                $currentPath = implode("/", $this->currentPath);

                $checked = (strpos($file, $currentPath) !== false) ? true : false;
                $li = '<li>';
                $li .= '<label for="' . $key . '">' . $key . '</label>';
                $li .= '<input type="checkbox"' . (($checked) ? 'checked="checked"' : null) . 'id="' . $key . '">';
                $li .= $this->listTree($value, $file);
                $li .= '</li>';
                $ol .= $li;

                array_pop($this->currentPath);
                //$path .= "/".$value;
            }

            $level++;

        }
        $ol .= "</ol>";

        return $ol;
    }


    /* @author  Kevin van Zonneveld &lt;kevin@vanzonneveld.net>
     * @author  Lachlan Donald
     * @author  Takkie
     * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
     * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
     * @version   SVN: Release: $Id: explodeTree.inc.php 89 2008-09-05 20:52:48Z kevin $
     * @link      http://kevin.vanzonneveld.net/
     *
     * @param array $array
     * @param string $delimiter
     * @param boolean $baseval
     *
     * @return array
     */
    function explodeTree($array, $delimiter = '_', $baseval = false, $callback = null)
    {
        if (!is_array($array)) return false;
        $splitRE = '/' . preg_quote($delimiter, '/') . '/';
        $returnArr = array();
        foreach ($array as $key => $val) {
            // Get parent parts and the current leaf
            $parts = preg_split($splitRE, $key, -1, PREG_SPLIT_NO_EMPTY);
            $leafPart = array_pop($parts);

            // Build parent structure
            // Might be slow for really deep and large structures
            $parentArr = &$returnArr;
            foreach ($parts as $part) {

                if (!isset($parentArr[$part])) {
                    $parentArr[$part] = array();
                } elseif (!is_array($parentArr[$part])) {
                    if ($baseval) {
                        $parentArr[$part] = array('__base_val' => $parentArr[$part]);
                    } else {
                        $parentArr[$part] = array();
                    }
                }

                $parentArr = &$parentArr[$part];

                //Use a callback to do more stuff!
                if (!empty($callback) && is_callable($callback)) {
                    $callback($leafPart, $parentArr, $part, $val);
                }

            }

            // Add the final part to the structure
            if (empty($parentArr[$leafPart])) {
                $parentArr[$leafPart] = $val;
            } elseif ($baseval && is_array($parentArr[$leafPart])) {
                $parentArr[$leafPart]['__base_val'] = $val;
            }
        }
        return $returnArr;
    }


    public function parseDown($text)
    {

        return \Parsedown::instance()->text($text);
    }
}