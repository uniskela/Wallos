<?php

/**
 * Helpers for detecting / recreating an incomplete Wallos SQLite database.
 * An empty wallos.db can appear when PHP opens SQLite before createdatabase runs.
 */

if (!function_exists('wallos_database_needs_create')) {
    /**
     * @return bool True when the DB file is missing or has no usable core schema.
     */
    function wallos_database_needs_create($databaseFile)
    {
        if (!file_exists($databaseFile)) {
            return true;
        }

        try {
            $probe = new SQLite3($databaseFile, SQLITE3_OPEN_READONLY);
            $probe->busyTimeout(1000);
            $result = $probe->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user'");
            $hasUser = $result && $result->fetchArray(SQLITE3_ASSOC);
            $probe->close();

            return !$hasUser;
        } catch (Throwable $e) {
            return true;
        }
    }
}

if (!function_exists('wallos_remove_database_files')) {
    function wallos_remove_database_files($databaseFile)
    {
        foreach ([$databaseFile, $databaseFile . '-wal', $databaseFile . '-shm', $databaseFile . '-journal'] as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }
}
