<?php

namespace local_mentor_core\utils;

class mentor_core_service
{
    /**
     * Write log in console
     * 
     * @param string $message
     * @param bool $lignebreak
     * @return void
     */
    public static function debugtrace(string $message, bool $lignebreak = false): void
    {
        $date = date("Y-m-d H:i:s");
        $message = "... [$date] $message";

        if ($lignebreak)
            $message = "\n$message";

        mtrace($message);
    }
}
