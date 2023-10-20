<?php
$db = $argv[1] ?? __DIR__ . "/../../chatwtf.db";

$db = new PDO( "sqlite:" . $db );

$db->exec( '
    ALTER TABLE "conversations" ADD COLUMN "model" VARCHAR(64) NOT NULL DEFAULT "";
    ALTER TABLE "conversations" ADD COLUMN "mode" VARCHAR(16) NOT NULL DEFAULT "";
' );
