<?php

use Glpi\Application\View\TemplateRenderer;

class PluginMasiveConfig extends CommonDBTM {

   static private $_instance = NULL;
   static $rightname         = 'config';

   const USER_FROM_PARENT    = 0;
   
   const ENABLED             = 1;
   const DISABLED            = 0;

   const MIXED               = 0;
   const INCIDENT            = 1;
   const REQUEST             = 2;

   static function canCreate() {
      return Session::haveRight('config', UPDATE);
   }


   static function canView() {
      return Session::haveRight('config', READ);
   }


   static function getTypeName($nb=0) {
      return __('Setup');
   }


   function getName($with_comment=0) {
      return __('Massive Upload', 'cargamasiva');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType() == 'Project' ) {
         return self::getName();
      }
      return '';
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType() == 'Project') {
         self::showConfigForm($item);
      }
      return true;
   }
   
   /**
    * Check if the passed itemtype is in the blacklist
    *
    * @param  string $itemtype
    *
    * @return bool
    */
    public static function canItemtype($itemtype = '') {
      return (!class_exists($itemtype) || $itemtype == 'Ticket');
   }

   /**
    * Singleton for the unique config record
    */
    static function getInstance($ID) {

      if (!isset(self::$_instance)) {
         self::$_instance = new self();
         if (!self::$_instance->getFromDBByCrit(['projects_id' => $ID])) {
            self::$_instance->getEmpty();
         }
      }
      return self::$_instance;
   }

   /**
    * Default values for instance
    */
   public function post_getEmpty(){
      $this->fields['id']              = 0;
      $this->fields['users_id_tech']   = 0;
      $this->fields['itil_followup']   = 0;
      $this->fields['entities_id']     = NULL;
      $this->fields['is_recursive']    = 1;
      $this->fields['is_active']       = 1;
      $this->fields['ticket_type']     = self::MIXED;
   }

   /**
     * Get criteria to restrict to current entities of the user
     *
     * @param string $value             entity ID used for look in database
     *
     * @return PluginEngageConfig of instance
     */
   public static function getConfigForEntity($value = '') {
      // !='0' needed because consider as empty
      $dbu = new DbUtils();
      $table = getTableForItemtype('PluginEngageConfig');
      $field = "$table.entities_id";

      $ancestors = [];
      if (is_array($value)) {
         $ancestors = $dbu->getAncestorsOf("glpi_entities", $value);
         $ancestors = array_diff($ancestors, $value);
      } else if (strlen($value) == 0) {
         $ancestors = $_SESSION['glpiparententities'] ?? [];
      } else {
         $ancestors = $dbu->getAncestorsOf('glpi_entities', $value);
      }
      array_push($ancestors,$value);
      $ancestors = array_reverse($ancestors);
      $config = new self();
      foreach ($ancestors as $key => $value) {
         if (!$config->getFromDBByCrit(['entities_id' => $value])) {
            $config->getEmpty();
            }
            
         if($config->isNewItem() 
            || ($config->fields['users_id_tech'] == PluginMasiveConfig::USER_FROM_PARENT
               && !$config->fields['is_active'] == PluginMasiveConfig::DISABLED)){
            continue;
         }

         if(!isset($config->fields['is_active']) || $config->fields['is_active'] == PluginEngageConfig::DISABLED){
            break;
         }

         return $config;
      }
      return $config->getEmpty();
   }

   /**
    * Singleton for the unique config record
    */
    static function showConfigForm(Project $project) {

      $config = self::getInstance($project->getEntityID());

     if($config->fields['is_active']){
	     echo '<form method="post" enctype="multipart/form-data">
                '.Html::hidden('masiveupload_form').'
                <input type="hidden" name="_glpi_csrf_token" value="'.Session::getNewCSRFToken().'">
                <table class="tab_cadre_fixe">
                    <tr class="tab_bg_1">
                        <td>
                            <label for="uploaded_file">'.__('Select a CSV file to upload:', 'masiveupload').'</label>
                        </td>
                        <td>
                            <input type="file" name="uploaded_file" id="uploaded_file" accept=".csv" required>
                        </td>
                    </tr>
                    <tr class="tab_bg_1">
		     <td colspan="2" class="center">
		      <input type="submit" name="submit" value="'.__('Upload', 'masive').'" class="submit">
		     </td>
                    </tr>
                </table>
              </form>';
	}
      
      return true;
   }

    public static function uploadFile($file) {
        // Validar el archivo
        if ($file['error'] != UPLOAD_ERR_OK) {
            return [
                'status' => 'error',
                'message' => __('File upload error', 'masiveupload')
            ];
        }

        // Definir el directorio de subida
        $upload_directory = GLPI_ROOT . '/files/_plugins/masiveupload/';
        
        // Crear el directorio si no existe
        if (!is_dir($upload_directory)) {
            mkdir($upload_directory, 0777, true);
        }

        // Definir la ruta completa del archivo
        $file_path = $upload_directory . basename($file['name']);

        // Mover el archivo subido al directorio de destino
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $result = self::processCSV($file_path);
            if ($result['status'] == 'success') {
                return [
                    'status' => 'success',
                    'message' => __('File uploaded and processed successfully', 'masiveupload')
                ];
            } else {
                return $result;
            }
        } else {
            return [
                'status' => 'error',
                'message' => __('File upload failed', 'masiveupload')
            ];
        }
    }

    public static function processCSV($file_path) {
        global $DB;

        if (($handle = fopen($file_path, "r")) !== FALSE) {
            $row = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                if ($row == 1) {
                    // Assuming the first row contains column headers, skip it.
                    continue;
                }

                $task_name = $data[0];
                $task_description = $data[1];
                $task_due_date = $data[2];
                $task_project_id = $data[3];

                // Crear una nueva tarea en GLPI
                $query = "INSERT INTO `glpi_tasks` (`name`, `content`, `duedate`, `itemtype`, `items_id`)
                          VALUES ('".$DB->escape($task_name)."', '".$DB->escape($task_description)."', '".$DB->escape($task_due_date)."', 'Project', '".$DB->escape($task_project_id)."')";
                if (!$DB->query($query)) {
                    return [
                        'status' => 'error',
                        'message' => __('Error processing CSV file', 'masiveupload')
                    ];
                }
            }
            fclose($handle);
        } else {
            return [
                'status' => 'error',
                'message' => __('Unable to open CSV file', 'masiveupload')
            ];
        }

        return [
            'status' => 'success',
            'message' => __('CSV file processed successfully', 'masiveupload')
        ];
    }


}
