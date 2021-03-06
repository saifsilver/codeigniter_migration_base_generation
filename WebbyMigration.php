<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Webby Migration Library (Improved As Per CI3)
 *
 * Create a base file for migrations to start off with;
 *
 * @author Saif Sulaiman <saif@webberoo.in> 
 * @license Free to use and abuse
 * @version 0.5 Beta
 *
 */
class WebbyMigration {

    var $db_user;
    var $db_pass;
    var $db_host;
    var $db_name;
    var $email;
    var $tables = '*';
    var $newline = '\n';
    var $write_file = true;
    var $file_name = '';
    var $file_per_table = true;
    var $path = 'migrations';
    var $skip_tables = array();
    var $add_view = false;

    /*
     * defaults;
     */

    function __construct($params = null) {
        // parent::__construct();
        isset($this->ci) OR $this->ci = get_instance();
        $this->ci->db_master = $this->ci->db;
        $this->db_user = $this->ci->db_master->username;
        $this->db_pass = $this->ci->db_master->password;
        $this->db_host = $this->ci->db_master->hostname;
        $this->db_name = $this->ci->db_master->database;
        $this->path = APPPATH . $this->path;
        if ($params)
            $this->init_config($params);
    }

    /**
     * Init Config if there is any passed
     *
     *
     * @param type $params
     */
    function init_config($params = array()) { //apply config
        if (count($params) > 0)
        {
            foreach ($params as $key => $val)
            {
                if (isset($this->$key))
                {
                    $this->$key = $val;
                }
            }
        }
    }

    /**
     * Generate the file.
     *
     * @param string $tables
     * @return boolean|string
     */
    function generate($tables = null, $version_name = '001_create_base') {
        if ($tables)
            $this->tables = $tables;

        $return = '';
        /* open file */
        if ($this->write_file)
        {
            if (!is_dir($this->path) OR !is_really_writable($this->path))
            {
                $msg = "Unable to write migration file: " . $this->path;
                log_message('error', $msg);
                echo $msg;
                return;
            }

            if (!$this->file_per_table)
            {
                $file_path = $this->path . '/' . $this->file_name . '.sql';
                $file = fopen($file_path, 'w+');

                if (!$file)
                {
                    $msg = "no file";
                    log_message('error', $msg);
                    echo $msg;
                    return FALSE;
                }
            }
        }


        // if default, then run all tables, otherwise just do the list provided
        if ($this->tables == '*')
        {

            $query = $this->ci->db_master->query('SHOW full TABLES FROM ' . $this->ci->db_master->protect_identifiers($this->ci->db_master->database));

            $retval = array();


            if ($query->num_rows() > 0)
            {

                foreach ($query->result_array() as $row)
                {

                    $tablename = 'Tables_in_' . $this->ci->db_master->database;

                    if (isset($row[$tablename]))
                    {
                        /* check if table in skip arrays, if so, go next */
                        if (in_array($row[$tablename], $this->skip_tables))
                            continue;

                        /* check if views to be migrated */
                        if ($this->add_view)
                        {
                            ## not implemented ##
                            //$retval[] = $row[$tablename];
                        } else
                        {
                            /* skip views */
                            if (strtolower($row['Table_type']) == 'view')
                            {
                                continue;
                            }
                            $retval[] = $row[$tablename];
                        }
                    }
                }
            }

            $this->tables = array();
            $this->tables = $retval;
        } else
        {
            $this->tables = is_array($tables) ? $tables : explode(',', $tables);
        }

        ## if write file, check if we can
        if ($this->write_file)
        {

            /* make subdir */
            $path = $this->path . '/' . $this->file_name;

            if (!@is_dir($path))
            {

                if (!@mkdir($path, DIR_WRITE_MODE, true))
                {
                    return FALSE;
                }

                @chmod($path, DIR_WRITE_MODE);
            }

            if (!is_dir($path) OR !is_really_writable($path))
            {
                $msg = "Unable to write backup per table file: " . $path;
                log_message('error', $msg);

                return;
            }

            $file_path = $path . '/'.$version_name.'.php';   

            $file = fopen($file_path, 'w+');

            if (!$file)
            {
                $msg = 'No File';
                log_message('error', $msg);
                echo $msg;

                return FALSE;
            }
        }


        $up = '';
        $down = '';
        //loop through tables
        foreach ($this->tables as $table)
        {

            log_message('debug', print_r($table, true));

            $q = $this->ci->db_master->query('describe ' . $this->ci->db_master->protect_identifiers($this->ci->db_master->database . '.' . $table));
            // No result means the table name was invalid
            if ($q === FALSE)
            {
                continue;
            }

            $columns = $q->result_array();

            $q = $this->ci->db_master->query(' SHOW TABLE STATUS WHERE Name = \'' . $table . '\'');
            $engines = $q->row_array();


            $up .= "\n\t\t" . '## Create Table ' . $table . "\n";

            if(!empty($columns)){
                $up .= "\n\t\t" . '$this->dbforge->add_field(array(';     
                $keys = '';      
                foreach ($columns as $column)
                {

                    $type = explode(' ',$column['Type']);

                    $type0_arr = explode('(',$type[0]);

                    $constrain = '';
                    if(isset($type0_arr[1])){
                        $constrain = "'constraint'        => '".str_replace(')', '', $type0_arr[1])."',";
                    } else {
                        $constrain = "'constraint'        => FALSE,";
                    }

                    $default = ($column['Default'] ? ($column['Type'] == 'timestamp' ? $column['Default'] : '\'' . $column['Default'] . '\'') : FALSE);
                    if($default == FALSE){
                        $default ='';
                    } else {
                        $default ="'default'           => $default";
                    }

                    $up .= 
    "\n\t\t\t"."'$column[Field]' => array(
                'type'              => '$type0_arr[0]',
                'null'              => ".($column['Null'] == 'NO'?'TRUE':'FALSE').",
                $constrain
                'unsigned'          => ".(isset($type[1]) && $type[1] == 'unsigned'?'TRUE':'FALSE').",
                'auto_increment'    => ".($column['Extra'] == 'auto_increment'?'TRUE':'FALSE').",
                'unique'            => ".($column['Extra'] == 'unique'?'TRUE':'FALSE').",
                $default
            ),";

                    if ($column['Key'] == 'PRI'){
                        $keys .= "\n\t\t" . '$this->dbforge->add_key("' . $column['Field'] . '",TRUE);';
                    } else if(!empty($column['Key'])) {
                        $keys .= "\n\t\t" . '$this->dbforge->add_key("' . $column['Field'] . '",FALSE);';
                    }
                }     
            }

            $up .= "\n\t\t" .'));';
            $up .= "\n\t\t" .$keys;
            $up .= "\n\t\t" . '$this->dbforge->create_table("' . $table . '", TRUE);' . "\n";
            if (isset($engines['Engine']) and $engines['Engine'])
                $up .= "\t\t" . '$this->db->query(\'ALTER TABLE  ' . $this->ci->db_master->protect_identifiers($table) . ' ENGINE = ' . $engines['Engine']. '\');';


            $down .= "\t\t" . '### Drop table ' . $table . ' ##' . "\n";
            $down .= "\t\t" . '$this->dbforge->drop_table("' . $table . '", TRUE);' . "\n";

            /* clear some mem */
            $q->free_result();
        }

        ### generate the text ##
        $return .= '<?php ';
        $return .= 'defined(\'BASEPATH\') OR exit(\'No direct script access allowed\');' . "\n\n";
        $return .= 'class Migration_create_base extends CI_Migration {' . "\n";
        $return .= "\n\t" . 'public function up() {' . "\n";

        $return .= $up;
        $return .= "\n\t" . ' }' . "\n";

        $return .= "\n\t" . 'public function down()';
        $return .= "\t" . '{' . "\n";
        $return .= $down . "\n";
        $return .= "\t" . '}' . "\n" . '}';

        ## write the file, or simply return if write_file false
        if ($this->write_file)
        {
            fwrite($file, $return);
            fclose($file);
            echo "Create file migration with success!";
            return true;
        } else
        {
            return $return;
        }
    }

}

?>
