<?php

namespace App\Core;

use MongoDB\Client;
use MongoDB\Database as MongoDBDatabase;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use MongoDB\Driver\Exception\Exception as MongoDBDriverException;

class Database
{
    private static ?Client $client = null;
    private static ?MongoDBDatabase $database = null;

    private const MONGO_URI = "mongodb://localhost:27017";
    private const DB_NAME = "contexto";

    private static function getClient(): Client
    {
        if (self::$client === null) {
            try {
                self::$client = new Client(self::MONGO_URI);
                self::$client->listDatabases(); // Verificer forbindelse
            } catch (ConnectionTimeoutException $e) {
                error_log("MongoDB Connection Timeout: " . $e->getMessage());
                die("Kunne ikke forbinde til databasen. Prøv venligst igen senere.");
            } catch (MongoDBDriverException $e) {
                error_log("MongoDB Driver Error: " . $e->getMessage());
                die("Databasefejl. Kontakt venligst administratoren.");
            } catch (\Exception $e) {
                error_log("General MongoDB Error: " . $e->getMessage());
                die("En uventet fejl opstod med databaseforbindelsen.");
            }
        }
        return self::$client;
    }

    public static function getDatabase(): MongoDBDatabase
    {
        if (self::$database === null) {
            self::$database = self::getClient()->selectDatabase(self::DB_NAME);
        }
        return self::$database;
    }

    public static function getCollection(string $collectionName): \MongoDB\Collection
    {
        return self::getDatabase()->selectCollection($collectionName);
    }

    // Returnerer indeks for collection
    public static function listCollectionIndexes(string $collectionName): array
    {
        $collection = self::getCollection($collectionName);
        $indexes = [];

        foreach ($collection->listIndexes() as $index) {
            $indexes[] = $index;
        }

        return $indexes;
    }
}
