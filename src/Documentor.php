<?php

namespace Budkit\Docs;


define('BUDKIT_DOCS_PATH', __DIR__);


use Budkit\Docs\TokenReflection\Broker;

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
        list($files, $files_array) = $this->getAllPHPFiles($directory);

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

        $source = file_get_contents($directory . $file);
        $sections = $this->parseSource($source, $directory, $file);

        $this->render($sections, $file, $files, $files_array);

        return true;
    }


    /**
     * Parse the source code of a file into an array of documentation (comments)
     * and source code blocks. Also parse the docblock metadata.
     *
     * @param string $source
     * @return array
     */
    public function parseSource($source, $directory, $file)
    {
        $namespace = null;
        $sections = [];


        $this->broker = $broker = new Broker(new Broker\Backend\Memory());

        //Reflection File
        $rFile = $broker->processFile($directory . $file, true);

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


                    $methods = $class->getMethods();
                    $toc = [];

                    //$def_section["description"]["doc"]["type"] = $class->getName();
                    $cls_section["description"]["doc"]["toc"] = ["title" => "Methods", "list" => &$toc];


                    if (!empty($methods)) {
                        $this->processMethods($methods, $class, $sections, $toc);
                    }


                    //Constants
                    $constants = $class->getConstants();
                    $this->processConstants($constants, $sections);


                    //get parent classes
                    $parent = $class->getParentClass();

                    if (!empty($parent)) {
                        //get a reflection of each parent;
                        $this->processInheritance($parent, $sections, $cls_section);
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

    public function processMethods($methods, &$class, &$sections, &$toc, $showdoc = true)
    {

        foreach ($methods as $method) {

            //We only want to deal with methods declared in this class;
            if(!$class->hasOwnMethod($method->getName())) continue;

            $mtd_id = "method:" . $method->getShortName();

            //add the method name to the TOC
            //we don't want to show consructors, private or protected methods here;
            if (!$method->isProtected() && !$method->isPrivate() && $method->getShortName() !== "__construct") {
                $toc[$mtd_id] = $method->getShortName();
            }

            //Do we want to show the complete doc?
            if(!$showdoc) continue;

            $mtd_section = $this->section;
            $labels = [];

            $mtd_section["description"]["doc"]["type"] = "method";
            $mtd_section["description"]["doc"]["title"] = $method->getShortName();
            $mtd_section["description"]["doc"]["id"] = $mtd_id;

            if ($method->isFinal()) $labels["final"] = "";
            if ($method->isPublic()) $labels["public"] = "success";
            if ($method->isStatic()) $labels["static"] = "default";

            if ($method->isProtected()) $labels["protected"] = "black";
            if ($method->isPrivate()) $labels["private"] = "error";
            if ($method->isAbstract()) $labels["abstract"] = "warning";

            $mtd_section["description"]["doc"]["labels"] = $labels;

            if (!empty($method->getDocComment())) {
                $mtd_section["description"]["doc"]["annotations"] = $method->getAnnotations();
            }

            //$mtd_section["description"]["doc"]["body"] = $method->getDocComment();
            $mtd_section["description"]["code"] = str_ireplace($method->getDocComment(), "", $method->getSource());

            $sections[] = $mtd_section;

        }

    }

    public function processInheritance($parent, &$sections, &$cls_section,  $itoc = [])
    {
        //$parentTree = [];
        if (!empty($parent)) {


            $cls_section["description"]["doc"]["parents"][] = $parent->getName();

            $_toc = [];

            $itd_section = $this->section;

            $itd_section["description"]["doc"]["type"] = "inherits";
            $itd_section["description"]["doc"]["title"] = $parent->getName();
            $itd_section["description"]["doc"]["toc"] = ["title" => "Inherited Methods", "list" => &$_toc];


            $methods = $parent->getMethods();

           print_r($parent);



            if(!empty($methods)) {
                $this->processMethods($methods, $parent, $sections, $_toc, false);
            }

           // print_r($_toc);

            if (!empty($_toc)) {
                $sections[] = $itd_section;
            }

            $parents_parent = $parent->getParentClass();

            if(!empty($parents_parent)){

                //Loop through all the parents parents, until no more parents
                $this->processInheritance($parents_parent, $sections, $cls_section, $itoc);

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
    public function render($sections, $file, $files, $files_array)
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


    function listTree($array = [], $file)
    {

        $level = 1;
        $ol = "<ol class='tree'>";

        foreach ($array as $key => $value) {

            if (!is_array($value)) {

                $this->currentPath[] = $value;

                $li = '<li class="file">';
                $li .= '<a href="?file=' . rawurlencode($value) . '">' . $key . '</a>';
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
    function explodeTree($array, $delimiter = '_', $baseval = false)
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