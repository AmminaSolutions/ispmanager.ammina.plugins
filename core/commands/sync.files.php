<?php
$files = \AmminaISP\Core\FilesSynchronizer::getInstance();
$files->run(true);
$templates = \AmminaISP\Core\TemplateSynchronizer::getInstance();
$templates->run(true);
