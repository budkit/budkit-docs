budkit/docs
=====

The PHP documentation generator that uses your existing source code! Based on the awesome [Docco](http://jashkenas.github.com/docco/) Javascript documentation generator (but with improvements for PHP!).
This package builds on the work [xeoncross/pocco](http://github.com/xeoncross/pocco) . Differences between this and the former, is the use of a fork of a much more complete Token Reflection library to process source files. See [livingstone/reflector](http://github.com/livingstone/reflector)

##Dependencies

- php >= 5.4.0
- [livingstone/reflector](http://github.com/livingstonef/reflector) : "*"
- "erusev/parsedown": "*"

## Example

First you need to install the library using [Composer](http://getcomposer.org/doc/00-intro.md#globally). Create a `composer.json` file in your documentation folder and type this:

	{
	    "repositories":[
	       {"type": "git", "url": "https://github.com/budkit/budkit-docs.git"},
	    ]
		"require": {
			"budkit/docs": "dev-master"
		}
	}


After you have created a composer.json file you can install budkit/docs.

	$ composer install

Next, create an index.php file in the directory in which you will like to create the docs e.g in your package docs folder.

	<?php

	//Use composer
    $loader = require '../../../autoload.php';

    if (!class_exists('Budkit\Docs\Documentor')){
        header('HTTP/1.0 404 Not Found');
        die("404 - To see these docs you need to have the budkit/docs dependency installed");
    }

    //Tell the documentor where your source files are stored
    $dir = realpath('../src') . '/';

    $default_file_to_show = null;
    $requested_file_to_show = isset($_GET['file']) ? $_GET['file'] : null;

    $docs = new \Budkit\Docs\Documentor();

    if (isset( $_GET['save'] ) ){
        if( $docs->saveHTML($dir) ) {
            //header('Location: '.dirname($dir)."/docs/index.html", true, 301);
            exit("<a href='file://".dirname($dir)."/docs/index.html'>Read The Docs</a><br /><br />NB. Clicking on this link (link to newly created index) does not work in some browsers (e.g safari does now allow opening local files), right-click and copy link or open in new tab.<br/>P.S. Docs from source <code>".$dir."</code> saved in <code>".dirname($dir)."/docs/</code>");

        }
    }else {
        if (!$docs->display($dir, $default_file_to_show, $requested_file_to_show)) {
            header('HTTP/1.0 404 Not Found');
            die("404 - File not Found");
        }
    }


And that's it. If you will like to save static html output. use `/path/to/docs/index.php?save=true`. Make sure that the process running this script can write to the parent directory of the source files. e.g. if documenting `/framework/src` make sure it can read/write into `/framework` to create `/framework/docs`

The Budkit framework reference uses this library  [budkit/framework API](http://budkit.github.io/budkit-framework/Budkit/Application/Instance.php.html)