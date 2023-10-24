<?php
$db = $argv[1] ?? __DIR__ . "/../../chatwtf.db";

$db = new PDO( "sqlite:" . $db );

$db->exec( '
    ALTER TABLE "messages" ADD COLUMN "function_name" VARCHAR(64) NULL DEFAULT NULL;
    ALTER TABLE "messages" ADD COLUMN "function_arguments" TEXT NULL DEFAULT NULL;
' );
