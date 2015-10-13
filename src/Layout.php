<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php print basename($file, '.php'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <link href="../../../budkit/docs/src/assets/kube.css" type="text/css" rel="stylesheet"/>
    <link rel="stylesheet" href="../../../budkit/docs/src/assets/highlight/styles/railscasts.css">

    <link href="../../../budkit/docs/src/assets/styles.css" type="text/css" rel="stylesheet"/>

    <script src="../../../budkit/docs/src/assets/highlight/highlight.pack.js"></script>
    <script>hljs.initHighlightingOnLoad();</script>
</head>
<body>
<div class="sidebar column">
    <?php print_R($this->listTree($tree, $file)); ?>
</div>
<div class="content">
    <div class="background">
        <div class="codeblock"></div>
    </div>
    <?php $depth = 0; ?>
    <?php foreach ($sections as $section => $partials) : ?>

        <?php foreach ($partials as $partial => $fragment) : ?>

            <?php //echo $partial; print_r($fragment); ?>
            <?php $depth ++; ?>
            <?php if (empty($fragment["doc"]) && empty($fragment["code"])) continue ?>

            <?php switch ((string)$partial) : default :  ?>
                <div class="literal column">
                    <div class="codedoc">
                        <?php if($depth > 3 ) : ?>
                            <a class="to-top" href="">Back To Top</a>
                        <?php endif; ?>
                        <?php if (isset($fragment["doc"])) : ?>
                            <div class="doc">

                                <?php if (isset($fragment["doc"]["subject"])) : ?>
                                    <div class="req"><?php print($fragment["doc"]["subject"]); ?></div>
                                <?php endif; ?>

                                <?php if (isset($fragment["doc"]["title"])) : ?>
                                    <h1 id="<?php print($fragment["doc"]["id"]); ?>">

                                        <?php if (isset($fragment["doc"]["type"])) : ?>
                                            <span class="label label-primary"><?php print($fragment["doc"]["type"]); ?></span>
                                        <?php endif; ?>

                                        <?php if (isset($fragment["doc"]["labels"]) && !empty($fragment["doc"]["labels"])) : ?>
                                                <?php foreach ($fragment["doc"]["labels"] as $label=>$class) : ?>
                                                    <span class="label label-<?php print($class); ?>" <?php print(empty($class)?"outline":""); ?>><?php print($label); ?></span>
                                                <?php  endforeach ?>
                                        <?php endif; ?>

                                        <?php print($fragment["doc"]["title"]); ?>


                                    </h1>
                                    <?php if (isset($fragment["doc"]["parents"])) : ?>
                                        <p>
                                            <?php foreach ($fragment["doc"]["parents"] as $p=>$parent) : ?>
                                                <span class="label label-default" outline>Extends</span>
                                                    <a href="?file=<?php print(str_ireplace("\\", "/", $parent) ) ?>.php"><span class="small"><?php print($parent); ?></span></a>
                                               <br />
                                            <?php  endforeach ?>
                                        </p>
                                        <?php endif; ?>

                                    <?php if (isset($fragment["doc"]["interfaces"])) : ?>
                                        <p>

                                       <?php foreach ($fragment["doc"]["interfaces"] as $interface) : ?>
                                            <span class="label label-default" outline>Implements</span>
                                                <a href="?file=<?php print(str_ireplace("\\", "/", $interface) ) ?>.php"><span class="small"><?php print($interface); ?></span></a>
                                            <br />
                                        <?php  endforeach ?>
                                        </p>

                                    <?php endif; ?>

                                <?php endif; ?>

                                <?php if (isset($fragment["doc"]["annotations"])) : $annotations = $fragment["doc"]["annotations"]; ?>
                                    <?php foreach($annotations as $k=>$annotation) : ?>
                                        <?php if( !in_array(trim($k), ["short_description","long_description"])) { ?>
                                        <dl>
                                            <dt><?php print(ucfirst( $k ) ); ?></dt>
                                            <?php if (is_array($annotation) ){ ?>
                                                <dd><?php print($this->parseDown( reset($annotation) ) ); ?></dd>
                                            <?php }else{ ?>
                                               <dd><?php print( $this->parseDown($annotation) ); ?></dd>
                                            <?php } ?>

                                        </dl>
                                        <?php }else{ ?>
                                            <p><?php print( $this->parseDown( $annotation) ); ?></p>
                                        <?php } ?>
                                    <?php endforeach; ?>

                                <?php endif; ?>



                                <?php if (isset($fragment["doc"]["toc"])) : $toc = $fragment["doc"]["toc"]; ?>

                                    <dl>
                                        <dt><?php print($toc["title"]) ?></dt>
                                        <dd>
                                            <?php $level = 0; ?>
                                            <row>
                                                <?php foreach($toc["list"] as $toc_id=>$toc_title): ?>
                                                    <?php if ($level % 2 == 0 ): ?>
                                                        </row><row>
                                                        <?php endif; ?>
                                                        <column cols="6"><a href="#<?php print($toc_id) ?>"><?php print($toc_title) ?>()</a></column>
                                                    <?php $level++ ?>
                                                <?php endforeach; ?>
                                            </row>
                                        </dd>

                                    </dl>

                                <?php endif; ?>


                                <?php if (isset($fragment["doc"]["body"])) : ?>
                                    <?php print($fragment["doc"]["body"]); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="codeblock">
                        <?php if (isset($fragment["code"])) : ?>
                            <pre>
                                    <code
                                        class="php"><?php print htmlspecialchars(rtrim($fragment["code"]), ENT_QUOTES, 'utf-8'); ?></code>
                                </pre>
                        <?php endif; ?>
                    </div>
                </div>
                <?php break; ?>
            <?php endswitch; ?>


        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
</body>
</html>