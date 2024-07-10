<?php

/**
 * -------------------------------------------------------------------------
 * masive plugin for GLPI
 * Copyright (C) 2024 by the masive Development Team.
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * --------------------------------------------------------------------------
 */

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_masive_install()
{
   global $CFG_GLPI, $DB;

   $default_charset = DBConnection::getDefaultCharset();
   $default_collation = DBConnection::getDefaultCollation();
   $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

   $migration = new Migration(PLUGIN_MASIVE_VERSION);

   $table = getTableForItemtype('PluginMasiveConfig');

   if (!$DB->allow_signed_keys){
      $default_key_sign = "unsigned";
      $migration->displayMessage("You need consider review config_db, your database should allow SignedKeys, if yes please configure it and reinstall.");
      $migration->log("You need consider review config_db, your database should allow SignedKeys, if yes please configure it and reinstall.",true);
   }

   if (!$DB->tableExists($table)) {
      
      $query = "CREATE TABLE IF NOT EXISTS `{$table}` (
               `id` INT {$default_key_sign} NOT NULL auto_increment,
                  `users_id_tech` INT DEFAULT 0,
                  `itil_followup` INT DEFAULT 0,
                  `entities_id` INT DEFAULT NULL,
                  `is_recursive` INT DEFAULT 1,
                  PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
      $DB->queryOrDie($query, $DB->error());

      $migration->displayMessage("Table `{$table}` was created.");
   }

   $migration->addField($table,'is_active','boolean',['value' => '1']);
   $migration->displayMessage("New feature for disable interaction.");
   $migration->addField($table,'ticket_type','int',['value' => '0']);
   $migration->displayMessage("New feature for restrict incident or demand ticket.");

   $migration->executeMigration();
   $migration->displayMessage("Installation of Engage plugin was executed.");

   return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_masive_uninstall()
{
     global $DB;
   $config = getTableForItemtype('PluginEngageConfig');

   $tables = [
      $config
   ];

   foreach ($tables as $table) {

      $tablename = $table;

      if ($DB->tableExists($tablename)) {
         $DB->queryOrDie(
            "DROP TABLE `$tablename`",
            $DB->error()
         );
      }
   }
   
   return true;
}
