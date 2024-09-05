PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS "conversations" (
	"id" INTEGER NOT NULL  ,
	"title" VARCHAR(64) NOT NULL  ,
	"model" VARCHAR(64) NOT NULL  ,
	"mode" VARCHAR(16) NOT NULL  ,
	PRIMARY KEY ("id")
);
CREATE TABLE IF NOT EXISTS "messages" (
	"id" INTEGER NOT NULL  ,
	"role" VARCHAR(13) NOT NULL  ,
	"content" TEXT NOT NULL  ,
	"function_name" VARCHAR(64) NULL DEFAULT NULL  ,
	"function_arguments" TEXT NULL DEFAULT NULL  ,
	"timestamp" DATETIME NOT NULL  ,
	"conversation" INTEGER NOT NULL  ,
	PRIMARY KEY ("id")
);
CREATE INDEX "conversation" ON "messages" ("conversation");
COMMIT;
