<?php
    /*
     * Saffire Shortcode Inserter -- SaffSQL
     *---------------------------------------------------------------------
     * Provides all the various sending and receiving functionality needed
     * for the saffire-popups-anywhere plugin.
     *********************************************************************/

    namespace WordPressDatabaseConnector {

        // Do Not Allow Direct Access to This Script File
        if ( ! defined( 'ABSPATH' ) )
            exit();

        /* Includes ******************************************************/
        try {

            // Classic_PHP Includes
            if ( ! class_exists( '\ClassicPHP\MySQLPDO', false ) ) {

                include_once plugin_dir_path( __FILE__ ) .
                    'includes/classic_php/databases/mysql_pdo.php';
            }
            if ( ! class_exists( '\ClassicPHP\ErrorHandling', false ) ) {
                include_once plugin_dir_path( __FILE__ ) .
                    'includes/classic_php/misc/error_handling.php';
            }
            if ( ! class_exists( '\ClassicPHP\ArrayProcessing', false ) ) {
                include_once plugin_dir_path( __FILE__ ) .
                    'includes/classic_php/data_types/array_processing.php';
            }
        }
        catch ( Error $e ) {

            throw new Exception(
                'ClassicPHP could not be found. Please ensure ClassicPHP is included with the plugin at ' . plugin_dir_path( __FILE__ ),
                404
            );
        }
        include_once plugin_dir_path( __FILE__ ) .
            'admin/admin-settings.php';

        /* Aliases Used */
        use \PDO as PDO;
        use \ClassicPHP\MySQLPDO as MySQLPDO;
        use \ClassicPHP\ErrorHandling as ErrorHandling;
        use \ClassicPHP\ArrayProcessing as ArrayProcessing;

        class WPDatabaseConnector {

            private $wpdb;
            private $saff_tables;
            private $sql;
            private $available_fields;
            private $mysql_pdo;
            private $error_handling;
            private $array_processing;

            public $pdo_statements;

            public function __construct() {

                /* Declaration *******************************************/
                global $wpdb;
                $this->wpdb = $wpdb;

                // Error Handling Variables
                $this->error_handling = new ErrorHandling();

                $this->array_processing = new ArrayProcessing();

                /* Tables */
                $this->tables =
                    [
                        'settings' =>
                            $wpdb->prefix . 'saffshortcodeinserter_settings',
                        'shortcodes' =>
                            $wpdb->prefix . 'saffshortcodeinserter_shortcodes',
                    ];

                try {

                    // Instantiate PDO Database Connection
                    $this->sql = new PDO(
                        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
                        DB_USER,
                        DB_PASSWORD
                    );

                    // Set the PDO Error Mode to Exception for Debugging
                    // https://www.php.net/manual/en/pdo.error-handling.php
                    if ( null !== constant( 'WP_DEBUG' ) ) {

                        $this->sql->setAttribute(
                            PDO::ATTR_ERRMODE,
                            PDO::ERRMODE_EXCEPTION );
                    }

                    // Instantiate PDO Helper Object
                    $this->mysql_pdo = new MySQLPDO( $this->sql );
                }
                catch ( PDOException $e ) {

                    $this->error_handling->throw_error(
                        'Saffire Shortcode Inserter plugin cannot connect '
                            . 'to the database because: '
                            . $e->getMessage(),
                        'error',
                        true );
                }
            }

            public function __destruct() {

                /* Close PDO MySQL Connection ****************************/
                $this->sql = null;
            }

            /** @method create_settings_table *****************************
             * Creates the saffshortcodeinserter_settings table in the
             * database.
             *-------------------------------------------------------------
            * @param void
            * @return void
            **************************************************************/
            public function create_settings_table() {

                /* Definition ********************************************/
                $query;
                $table_exists = false;
                $table_type = 'settings';

                // Table Schema
                $fields = [
                    [
                        'name' => 'option_group_id',
                        'data_type' => 'int(15)',
                        'attributes' =>
                            'unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY'
                    ],
                    [
                        'name' => 'drop_table_on_deactivate',
                        'data_type' => 'char(1)',
                        'attributes' => 'DEFAULT \'N\''
                    ],
                ];

                /* Processing ********************************************/
                /* Query the Database for the Table ---------------------*/
                if (
                    false !== $this->mysql_pdo->validate_table_names(
                        $this->tables[ $table_type ], 'array' ) ) {

                    $table_exists = true;
                }

                /* Create the Table -------------------------------------*/
                if ( ! $table_exists ) {

                    $this->create_table( $table_type, $fields );

                    $this->insert_settings( [ 'drop_table_on_deactivate' => false ] );
                }

                /* Update Existing Table --------------------------------*/
                else {

                    $this->alter_table( $table_type, $fields );
                }
            }

            /** @method create_popups_table *******************************
             * Creates the saffpopupsanywhere_popups table in the
             * database.
             *-------------------------------------------------------------
            * @param void
            * @return void
            **************************************************************/
            public function create_popups_table() {

                $query;
                $table_exists = false;
                $table_type = 'shortcodes';

                // Popups Table Schema
                $fields = [
                    [
                        'name' => 'popup_id',
                        'data_type' => 'int(15)',
                        'attributes' =>
                            'unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY'
                    ],
                    [
                        'name' => 'content',
                        'data_type' => 'text',
                        'attributes' => 'DEFAULT \'\''
                    ],
                    [
                        'name' => 'required_pages',
                        'data_type' => 'text',
                        'attributes' => 'DEFAULT \'\''
                    ],
                    [
                        'name' => 'required_url_query',
                        'data_type' => 'text',
                        'attributes' => 'DEFAULT \'\''
                    ],
                    [
                        'name' => 'redirect',
                        'data_type' => 'tinytext',
                        'attributes' => 'DEFAULT \'\''
                    ],
                    [
                        'name' => 'redirect_new_tab',
                        'data_type' => 'boolean',
                        'attributes' => 'DEFAULT FALSE'
                    ],
                    [
                        'name' => 'custom_css',
                        'data_type' => 'text',
                        'attributes' => 'DEFAULT \'\''
                    ],
                ];

                /* Processing ********************************************/
                /* Query the Database for the Table ---------------------*/
                if (
                    false !== $this->mysql_pdo->validate_table_names(
                        $this->tables[ $table_type ], 'array' ) ) {

                    $table_exists = true;
                }

                /* Create the Table -------------------------------------*/
                if ( ! $table_exists ) {

                    $this->create_table( $table_type, $fields );
                }

                /* Update Existing Table --------------------------------*/
                else {

                    $this->alter_table( $table_type, $fields );
                }
            }

            /** @method drop_settings_table **************************
             * Drops the saffshortcodeinserter_settings table from the
             * database.
             *-------------------------------------------------------------
            * @param void
            * @return void
            *************************************************************/
            public function drop_settings_table() {

                /* Processing ********************************************/
                $this->sql->query(
                    'DROP TABLE IF EXISTS '
                    . $this->tables['settings'] . ';');
            }

            /** @method drop_popups_table *********************************
             * Drops the saffpopupsanywhere_popups table from the database.
             *-------------------------------------------------------------
            * @param void
            * @return void
            *************************************************************/
            public function drop_popups_table() {

                /* Processing ********************************************/
                $this->sql->query(
                    'DROP TABLE IF EXISTS '
                    . $this->tables['shortcodes'] . ';');
            } 
            
            /** @method insert_settings ***********************************
             * Adds a new record of settings to the table.
             *-------------------------------------------------------------
             * @param string[] $setting_fields -- May contain the following
             *      keys:
             * 
             *      drop_table_on_deactivate
             * @return boolean
             *************************************************************/
            public function insert_settings(
                array $setting_fields ) {

                /* Declaration *******************************************/
                $query;             // Database Query String
                $table_type_queried = 'settings';
                $pdo_statement;     // pdo_statement object
                $pdo_value_type;    // PDO::PARAM_X parameter value type

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                /* Validate $setting_fields by Removing Invalid Fields */
                $setting_fields = $this->remove_invalid_fields(
                    $table_type_queried,
                    $setting_fields );

                // Return False If Problem (e.g., Fields Don't Exist)
                if ( false === $setting_fields ) {

                    return false;
                }

                /* Build Query ------------------------------------------*/
                $query =
                    'INSERT INTO '
                    . $this->tables[ $table_type_queried ] . ' (';

                // Use $setting_fields to Build Field List
                foreach (
                    $setting_fields
                        as $setting_field_key => $setting_field_value ) {

                    $query .=
                        $setting_field_key . ', ';
                }

                $query[ strlen( $query ) - 2 ] = ')';

                // Use $setting_fields to Build VALUES Clause
                $query .= 'VALUES (';

                foreach (
                    $setting_fields
                        as $setting_field_key => $setting_field_value ) {

                    $query .=
                        ':'
                        . $setting_field_key . ', ';
                }

                $query[ strlen( $query ) - 2 ] = ')';

                /* Prepare Query ----------------------------------------*/
                $pdo_statement = $this->sql->prepare( $query );
                
                /* Bind Parameters */
                // Bind Set Clause Parameters
                foreach (
                    $setting_fields
                        as $setting_field_key => $setting_field_value ) {

                    // Determine PDO Value Type
                    if (
                        'drop_table_on_deactivate' ===
                            $setting_field_key ) {

                        $pdo_value_type = PDO::PARAM_BOOL;
                    }
                    else {

                        $pdo_value_type = PDO::PARAM_STR;
                    }
                    
                    // Bind Parameter
                    $pdo_statement->bindParam(
                        ':' . $setting_field_key,
                        $setting_fields[ $setting_field_key ],
                        $pdo_value_type );
                }

                /* Execute Query ----------------------------------------*/
                $pdo_statement->execute();

                /* Return ************************************************/
                return true;
            }
            
            /** @method update_settings ***********************************
             * Updates the settings table.
             *-------------------------------------------------------------
             * @param string[] $setting_fields -- May contain the following
             *      keys:
             * 
             *      drop_table_on_deactivate
             * @return boolean
             *************************************************************/
            public function update_settings(
                array $setting_fields ) {

                /* Declaration *******************************************/
                $query;             // Database Query String
                $table_type_queried = 'settings';
                $pdo_statement;     // pdo_statement object
                $pdo_value_type;    // PDO::PARAM_X parameter value type

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                /* Validate $setting_fields by Removing Invalid Fields */
                $setting_fields = $this->remove_invalid_fields(
                    $table_type_queried,
                    $setting_fields );

                // Return False If Problem (e.g., Fields Don't Exist)
                if ( false === $setting_fields ) {

                    return false;
                }

                /* Verify Setting Exists */
                $pdo_result =
                    $this->query_settings(
                        array_keys( $setting_fields ) );

                if ( $pdo_result->fetch( PDO::FETCH_ASSOC ) === false ) {

                    return false;
                }

                /* Build Query ------------------------------------------*/
                $query =
                    'UPDATE ' . $this->tables[ $table_type_queried ]
                    . ' SET';

                // Use $setting_fields to Build Query
                foreach (
                    $setting_fields
                        as $setting_field_key => $setting_field_value ) {

                    $query .=
                        ' ' . $setting_field_key . ' = :'
                        . $setting_field_key . ',';
                }

                $query[ strlen( $query ) - 1 ] = ';';

                /* Prepare Query ----------------------------------------*/
                $pdo_statement = $this->sql->prepare( $query );

                /* Bind Parameters */
                // Bind Set Clause Parameters
                foreach (
                    $setting_fields
                        as $setting_field_key => $setting_field_value ) {

                    // Determine PDO Value Type
                    if (
                        'drop_table_on_deactivate' ===
                            $setting_field_key ) {

                        $pdo_value_type = PDO::PARAM_BOOL;
                    }
                    else {

                        $pdo_value_type = PDO::PARAM_STR;
                    }
                    
                    // Bind Parameter
                    $pdo_statement->bindParam(
                        ':' . $setting_field_key,
                        $setting_fields[ $setting_field_key ],
                        $pdo_value_type );
                }

                /* Execute Query ----------------------------------------*/
                $pdo_statement->execute();

                /* Return ************************************************/
                return true;
            }

            /** @method query_settings ************************************
             * Returns settings from the saffpopupsanywhere table via a
             * pdo_statement object.
             *-------------------------------------------------------------
             * @param mixed string[] string $table_fields_saught
             * @param bool $prepare_pdo_statement_once
             * @return pdo_statement
             * @return false
             *************************************************************/
            public function query_settings(
                $table_fields_saught = '*',
                bool $prepare_pdo_statement_once = true ) {

                /* Declaration *******************************************/
                $fields_saught_result;
                $field_string;

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                $fields_saught_result =
                    $this->validate_fields_available_for_query(
                        $table_fields_saught,
                        'settings' );

                // If Invalid Input, Default '*' Fields Array
                if (
                    is_int( $fields_saught_result )
                    || false === $fields_saught_result ) {

                    $table_fields_saught = [ '*' ];
                }
                elseif ( is_array( $fields_saught_result ) ) {

                    $table_fields_saught = $fields_saught_result;
                }

                /* Prepare Query String ---------------------------------*/
                $field_string = $table_fields_saught[0];

                for ( $i = 1; $i < count( $table_fields_saught ); $i++ ) {

                    $field_string .= ', ' . $table_fields_saught[$i];
                }

                /* Process Query ----------------------------------------*/
                // Only Prepare Statement if Not Already Prepared
                if (
                    ! $prepare_pdo_statement_once
                    || ! isset(
                        $this->pdo_statements['query_settings'] ) ) {

                    $this->pdo_statements['query_settings'] =
                        $this->sql->query(
                            'SELECT ' . $field_string . ' '
                            . 'FROM '
                            . $this->tables['settings'] . ';' );
                }

                /* Return ************************************************/
                return $this->pdo_statements['query_settings'];
            }

            /** @method query_all_popups **********************************
             * Returns all the popup data.
             *-------------------------------------------------------------
             * @param bool $prepare_pdo_statement_once
             * @return pdo_statement
             * @return false
             *************************************************************/
            public function query_all_popups(
                $table_fields_saught = '*',
                bool $prepare_pdo_statement_once = true ) {

                /* Declaration *******************************************/
                $field_string;
                $fields_saught_result;

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                $fields_saught_result =
                    $this->validate_fields_available_for_query(
                        $table_fields_saught,
                        'shortcodes' );

                // If Invalid Input, Default '*' Fields Array
                if (
                    is_int( $fields_saught_result )
                    || false === $fields_saught_result ) {

                    $table_fields_saught = [ '*' ];
                }
                elseif ( is_array( $fields_saught_result ) ) {

                    $table_fields_saught = $fields_saught_result;
                }

                /* Prepare Query String ---------------------------------*/
                $field_string = $table_fields_saught[0];

                for ( $i = 1; $i < count( $table_fields_saught ); $i++ ) {

                    $field_string .= ', ' . $table_fields_saught[$i];
                }

                /* Process Query ----------------------------------------*/
                // Only Prepare Statement if Not Already Prepared
                if (
                    ! $prepare_pdo_statement_once
                    || ! isset(
                        $this->pdo_statements['query_all_popups'] ) ) {

                    $this->pdo_statements['query_all_popups'] =
                        $this->sql->query(
                            'SELECT ' . $field_string . ' '
                            . 'FROM '
                            . $this->tables['shortcodes'] . ';' );
                }

                /* Return ************************************************/
                return $this->pdo_statements['query_all_popups'];
            }

            /** @method query_last_popup **********************************
             * Returns last popup in the database.
             *-------------------------------------------------------------
             * @param bool $prepare_pdo_statement_once
             * @return pdo_statement
             * @return false
             *************************************************************/
            public function query_last_popup(
                $table_fields_saught = '*',
                bool $prepare_pdo_statement_once = true ) {

                /* Declaration *******************************************/
                $fields_saught_result;
                $field_string;

                $query;

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                $fields_saught_result =
                    $this->validate_fields_available_for_query(
                        $table_fields_saught,
                        'shortcodes' );

                // If Invalid Input, Default '*' Fields Array
                if (
                    is_int( $fields_saught_result )
                    || false === $fields_saught_result ) {

                    $table_fields_saught = [ '*' ];
                }
                elseif ( is_array( $fields_saught_result ) ) {

                    $table_fields_saught = $fields_saught_result;
                }

                /* Prepare Query String ---------------------------------*/
                $field_string = $table_fields_saught[0];

                for ( $i = 1; $i < count( $table_fields_saught ); $i++ ) {

                    $field_string .= ', ' . $table_fields_saught[$i];
                }

                /* Process Query ----------------------------------------*/
                $query = 'SELECT ' . $field_string . ' '
                    . 'FROM '
                    . $this->tables['shortcodes'] . ' '
                    . 'ORDER BY popup_id DESC '
                    . 'LIMIT 1;';

                /* Process Query ----------------------------------------*/
                // Only Prepare Statement if Not Already Prepared
                if (
                    ! $prepare_pdo_statement_once
                    || ! isset(
                        $this->pdo_statements['query_last_popup'] ) ) {

                    $this->pdo_statements['query_last_popup'] =
                        $this->sql->query( $query );
                }

                /* Return ************************************************/
                return $this->pdo_statements['query_last_popup'];
            }

            /** @method update_all_popups *********************************
             * Updates the popups table.
             *-------------------------------------------------------------
             * @param string[] $new_popups -- May contain the following keys
             *      within sub arrays:
             * 
             *      popup_id
             *      content
             *      required_pages
             *      required_url_parameter
             *      redirect
             *      custom_css
             * @return boolean
             *************************************************************/
            public function update_all_popups(
                array $new_popups ) {

                /* Declaration *******************************************/
                $query;             // Database Query String
                $table_type_queried = 'shortcodes';
                $pdo_statement;     // pdo_statement object
                $pdo_value_type;    // PDO::PARAM_X parameter value type

                $pdo_result;
                $stored_popup_ids;     // Existing Popup IDs

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                /* Validate $new_popups by Removing Invalid Fields */
                foreach ( $new_popups as $popup_key => $popup ) {

                    $new_popups[ $popup_key ] = $this->remove_invalid_fields(
                        $table_type_queried,
                        $popup );
                }

                // Return False If Problem (e.g., Fields Don't Exist)
                if ( false === $new_popups ) {

                    return false;
                }

                /* Gather Existing Popup IDs */
                $pdo_result = $this->query_all_popups( ['popup_id'] );

                $stored_popup_ids = $pdo_result->fetchAll( PDO::FETCH_ASSOC );

                /* Create and Send Queries ------------------------------*/
                for ( $i = 0; $i < count( $new_popups ); $i++ ) {

                    $query =
                        'UPDATE ' . $this->tables[ $table_type_queried ]
                        . ' SET';
        
                    // Use $new_popups to Build Query
                    foreach (
                        $new_popups[ $i ]
                            as $popup_field_key => $popup_field ) {
    
                        $query .=
                            ' ' . $popup_field_key . ' = :'
                            . $popup_field_key . ',';
                    }
    
                    $query[ strlen( $query ) - 1 ] = ' ';

                    $query .= 'WHERE popup_id = :popup_id;';
    
                    /* Prepare Query ----------------------------------------*/
                    $pdo_statement = $this->sql->prepare( $query );
    
                    /* Bind Parameters */
                    // Bind Set Clause Parameters
                    foreach (
                        $new_popups[ $i ]
                            as $popup_field_key => $popup_field ) {
    
                        // Determine PDO Value Type
                        if ( 'redirect_new_tab' === $popup_field_key ) {

                            $pdo_value_type = PDO::PARAM_INT;
                        }
                        else {
                        
                            $pdo_value_type = PDO::PARAM_STR;
                        }
                        
                        // Bind Parameter
                        $pdo_statement->bindParam(
                            ':' . $popup_field_key,
                            $new_popups[ $i ][ $popup_field_key ],
                            $pdo_value_type );
                    }

                    $pdo_statement->bindParam(
                        ':popup_id',
                        $stored_popup_ids[ $i ]['popup_id'],
                        PDO::PARAM_INT );
    
                    /* Execute Query ----------------------------------------*/
                    $pdo_statement->execute();
                }

                /* Return ************************************************/
                return true;
            }

            /** @method create_new_popup_record ***************************
             * Adds a new popup record to the database.
             *-------------------------------------------------------------
             * @return boolean
             *************************************************************/
            public function create_new_popup_record() {

                /* Declaration *******************************************/
                $query;             // Database Query String

                $table_type_queried = 'shortcodes';

                /* Processing ********************************************/
                /* Create and Send Queries ------------------------------*/
                $query =
                    'INSERT INTO '
                    . $this->tables[ $table_type_queried ] . ' '
                    . 'VALUES()';

                /* Execute Query ----------------------------------------*/
                $this->sql->query( $query );

                /* Return ************************************************/
                return true;
            }

            /** @method remove_popup_record *******************************
             * Removes a popup record from the database. Defaults to the
             * last record in the database if no record given.
             *-------------------------------------------------------------
             * @return boolean
             *************************************************************/
            public function remove_popup_record( int $popup_id = -1 ) {

                /* Declaration *******************************************/
                $query;             // Database Query String
                $table_type_queried = 'shortcodes';
                $pdo_statement;     // pdo_statement object
                $pdo_value_type;    // PDO::PARAM_X parameter value type

                $pdo_result;
                $returned_record;

                /* Processing ********************************************/
                /* Create and Send Queries ------------------------------*/
                $query =
                    'DELETE FROM '
                    . $this->tables[ $table_type_queried ] . ' '
                    . 'WHERE popup_id = :popup_id';

                /* Prepare Query ----------------------------------------*/
                $pdo_statement = $this->sql->prepare( $query );

                // Determine PDO Value Type
                $pdo_value_type = PDO::PARAM_INT;

                /* Bind Parameters */
                if ( 0 < $popup_id ) {

                    // Bind Parameter
                    $pdo_statement->bindParam(
                        ':popup_id',
                        $popup_id,
                        $pdo_value_type );
                }
                else {

                    $pdo_result = $this->query_last_popup( ['popup_id'] );

                    $returned_record = $pdo_result->fetch( PDO::FETCH_ASSOC );

                    // Bind Parameter
                    $pdo_statement->bindParam(
                        ':popup_id',
                        $returned_record['popup_id'],
                        $pdo_value_type );
                }

                /* Execute Query ----------------------------------------*/
                $pdo_statement->execute();

                /* Return ************************************************/
                return true;
            }

            /** @method create_table **************************************
             * Creates a table in the database.
             *-------------------------------------------------------------
             * @param string $table_type -- The table type keys found in
             *      $this->tables
             * @param string[] $field_definitions
             * @return void
             * @return false -- On error
             *************************************************************/
            private function create_table(
                string $table_type,
                array $field_definitions ) {

                /* Declaration *******************************************/
                $query;
                $charset_collate = $this->wpdb->get_charset_collate();

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                if (
                    ! array_key_exists(
                        'name',
                        $field_definitions[0] )
                    || ! array_key_exists(
                        'data_type',
                        $field_definitions[0] )
                    || ! array_key_exists(
                        'attributes',
                        $field_definitions[0] ) ) {

                    return false;
                }

                if (
                    false ===
                        $this->is_table_type_valid( $table_type ) ) {

                    $table_type = 'settings';
                }

                /* Build Create Table Query */
                $query =
                    "CREATE TABLE IF NOT EXISTS "
                    . $this->tables[ $table_type ] . " (";

                // Add Field Definitions to Query
                for ( $i = 0; $i < count( $field_definitions ); $i++ ) {

                    $query .=
                        $field_definitions[ $i ]['name'] . ' '
                        . $field_definitions[ $i ]['data_type'] . ' '
                        . $field_definitions[ $i ]['attributes'];


                    if ( $i + 1 < count( $field_definitions ) ) {

                        $query .= ', ';
                    }
                    else {

                        $query .= ' ';
                    }
                }

                // Add Table Character Set to Query
                $query .= ") $charset_collate;";

                /* Perform Create Table Query */
                $this->sql->query($query);
            }

            /** @method alter_table ***************************************
             * Alters the table specified to match the field definitions
             * provided.
             * BEWARE: Fields not found in the input are permanently
             * removed from the database, along with all the data in
             * every record connected to that field!
             *-------------------------------------------------------------
             * @param string $table_type -- The table type keys found in
             *      $this->tables
             * @param string[] $field_definitions
             * @return void
             * @return false -- On error
             *************************************************************/
            private function alter_table(
                string $table_type,
                array $updated_field_definitions ) {

                /* Declaration *******************************************/
                $query;
                $field_found_match = false;
                $field_added = false;
                $fields_found;

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                if (
                    ! array_key_exists(
                        'name',
                        $updated_field_definitions[0] )
                    || ! array_key_exists(
                        'data_type',
                        $updated_field_definitions[0] )
                    || ! array_key_exists(
                        'attributes',
                        $updated_field_definitions[0] ) ) {

                    return false;
                }

                if (
                    false ===
                        $this->is_table_type_valid( $table_type ) ) {

                    $table_type = 'settings';
                }

                /* Query Existing Fields --------------------------------*/
                $fields_found =
                    $this->mysql_pdo->query_table_fields(
                        $this->tables[ $table_type ] );

                if ( false !== $fields_found ) {

                    $query =
                        "ALTER TABLE "
                        . $this->tables[ $table_type ] . " ";

                    /* Add Modified and Additional Fields to Query ------*/
                    for (
                        $i = 0;
                        $i < count( $updated_field_definitions );
                        $i++ ) {

                        $field_found_match = false;

                        // Don't Do Anything with Primary Key Fields
                        if (
                            false !==
                                strpos(
                                    $updated_field_definitions[
                                        $i
                                    ]['attributes'],
                                    'PRIMARY KEY' ) ) {

                            continue;
                        }

                        // Check If Original Field Found in Updated Fields
                        foreach ( $fields_found as $field_found ) {

                            if (
                                $updated_field_definitions[
                                    $i
                                ]['name'] ===
                                    $field_found ) {

                                $field_found_match = true;
                                break;
                            }
                        }

                        // Add New Field to Table
                        if ( ! $field_found_match ) {

                            $query .=
                                'ADD '
                                . $updated_field_definitions[
                                    $i
                                ]['name'] . ' '
                                . $updated_field_definitions[
                                    $i
                                ]['data_type'] . ' '
                                . $updated_field_definitions[
                                    $i
                                ]['attributes'] . ', ';
                        }

                        // Modify Field in Table
                        else {

                            $query .=
                                'MODIFY '
                                . $updated_field_definitions[
                                    $i
                                ]['name'] . ' '
                                . $updated_field_definitions[
                                    $i
                                ]['data_type'] . ' '
                                . $updated_field_definitions[
                                    $i
                                ]['attributes'] . ', ';
                        }
                    }

                    /* Add Dropped Fields to Query ----------------------*/
                    foreach ( $fields_found as $field_found ) {

                        $field_found_match = false;

                        // Check If Updated Field Found in Original Fields
                        for (
                            $i = 0;
                            $i < count( $updated_field_definitions );
                            $i++ ) {

                            if (
                                $updated_field_definitions[
                                    $i
                                ]['name'] ===
                                    $field_found ) {

                                $field_found_match = true;
                                break;
                            }
                        }

                        // Mark Unmatched Original Field as Obsolete
                        if ( ! $field_found_match ) {

                            $query .=
                                'DROP '
                                . $field_found . ', ';
                        }
                    }

                    // Remove Final ', ' and Append a Semicolon
                    $query =
                        substr(
                            $query,
                            0,
                            strlen( $query ) - 2 )
                        . ';';

                    /* Perform Alter Table Query ------------------------*/
                    $this->sql->query( $query );
                }
            }

            /** @method validate_string_in_query **************************
             * Returns true if the parameter doesn't have any invalid
             * characters in it.
             *-------------------------------------------------------------
             * @param string $string_in_query
             * @return bool
             *************************************************************/
            private function validate_string_in_query(
                string $string_in_query = '' ) {

                /* Declaration *******************************************/
                $is_good = true;
                $regex_match;

                /* Processing ********************************************/
                /* Validation */
                // Validate String Unless String = '*'
                if ( '*' !== $string_in_query ) {

                    // Return False If String Contains Numbers or Symbols
                    $regex_match = preg_match(
                        "/[0-9\.\,';:\\\{\}\|\?!@#\$%\^&\(\)\=\+\`*\ ]/",
                        $string_in_query );

                    if ( 1 === $regex_match ) {

                        $is_good = false;
                    }
                }

                /* Return ************************************************/
                return $is_good;
            }

            /** @method validate_fields_available_for_query ***************
             * Returns a validated field name array, or false if any input
             * field names don't exist in selected table.
             *-------------------------------------------------------------
             * @param string[] $fields_saught
             * @param string $table_type_queried
             * @return string[] $fields_saught
             * @return bool
             * @return int $field_match_found_count
             *************************************************************/
            private function validate_fields_available_for_query(
                $fields_saught,
                string $table_type_queried = 'shortcodes' ) {

                /* Declaration *******************************************/
                $pdo_statement;
                $field_match_found_count = 0;

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                /* Validate Table Type Being Queried */
                if (
                    'shortcodes' !== $table_type_queried
                    && 'settings' !== $table_type_queried ) {

                    $table_type_queried = 'shortcodes';
                }

                /* Validate Every Array Element */
                if ( is_array( $fields_saught ) ) {

                    foreach ( $fields_saught as $field ) {

                        // Exit If Query String is Bad
                        if ( ! $this->validate_string_in_query(
                            $field ) ) {

                            // Exit Prematurely
                            return false;
                        }
                    }
                }

                // If Not Array, Validate String and Make Array
                else {

                    if ( ! $this->validate_string_in_query(
                        $fields_saught ) ) {

                        // Exit Prematurely
                        return false;
                    }

                    $fields_saught = [ $fields_saught ];
                }

                /* Query Available Fields -------------------------------*/
                if (
                    false === $this->query_fields_available(
                        $table_type_queried ) ) {

                    return false;
                }

                /* Verify All Fields Saught Match Table's Fields --------*/
                if ( [ '*' ] !== $fields_saught ) {

                    $field_match_found_count = 0;

                    /* Count Matching Fields */
                    for ( $i = 0; $i < count( $fields_saught ); $i++ ) {

                        foreach (
                            $this->available_fields[
                                $table_type_queried ]
                            as $available_field ) {

                            if (
                                $fields_saught[ $i ]
                                    === $available_field ) {

                                $field_match_found_count++;
                                break;
                            }
                        }

                        // Return Last Match Index On First False Match
                        if ( $field_match_found_count - 1 < $i ) {

                            return $field_match_found_count - 1;
                        }
                    }
                }

                /* Return ************************************************/
                return $fields_saught;
            }

            /** @method query_fields_available ****************************
             * Queries one of the plugin tables for available fields,
             * stores an array of those fields in the private
             * $available_fields array class property (if the array doesn't
             * already exist), and returns true or false depending on
             * success querying the database.
             *-------------------------------------------------------------
             * @param string $table_type_queried
             * @return bool
             *************************************************************/
            private function query_fields_available(
                string $table_type_queried ) {

                /* Declaration *******************************************/
                $pdo_statement;
                $returned_record;
                $query;
                $available_fields;

                /* Processing ********************************************/
                /* Validation -------------------------------------------*/
                if ( 'shortcodes' !== $table_type_queried ) {

                    $table_type_queried = 'settings';
                }

                /* Perform Fields Query if Fields Data Doesn't Exist ----*/
                if ( ! isset(
                    $this->available_fields[ $table_type_queried ] ) ) {

                    $query =
                        'DESCRIBE '
                            . $this->tables[
                                $table_type_queried ];

                    $pdo_statement = $this->sql->query( $query );

                    try {

                        $returned_record =
                            $pdo_statement->fetchAll( PDO::FETCH_ASSOC );
                    }

                    catch( Exception $e ) {

                        return false;
                    }

                    if ( false !== $returned_record ) {

                        foreach ( $returned_record as $record ) {

                            $available_fields[] = $record['Field'];
                        }

                        $this->available_fields[ $table_type_queried ] =
                            $available_fields;
                    }
                    else {

                        return false;
                    }
                }

                return $this->available_fields[ $table_type_queried ];
            }

            /** @method remove_invalid_fields *****************************
             * Removes fields from an array of fields which do not exist in
             * the desired table.
             *-------------------------------------------------------------
             * @param string $table_type_queried
             * @return bool
             *************************************************************/
            private function remove_invalid_fields(
                string $table_type_queried,
                array $fields_list = [] ) {

                /* Definition ************************************************/
                $field_found;       // Does field exist in table?

                /* Processing ************************************************/
                /* Validate $fields_list Using Available Table Fields */
                // Query Available Fields
                if (
                    false === $this->query_fields_available(
                        $table_type_queried ) ) {

                    return false;
                }

                // Search $fields_list for Unavailable Fields; Remove
                foreach (
                    $fields_list
                        as $field_key => $field_value ) {

                    $field_found = false;

                    for (
                        $i = 0;
                        $i < count (
                            $this->available_fields[
                                $table_type_queried ] );
                        $i++ ) {

                        if (
                            $field_key ===
                                $this->available_fields[
                                    $table_type_queried ][ $i ] ) {

                            $field_found = true;
                            break;
                        }
                    }

                    if ( ! $field_found ) {

                        unset( $fields_list[ $field_key ] );
                    }
                }

                // Return False if Validated $fields_list Array is Empty
                if ( 1 > count( $fields_list ) ) {

                    return false;
                }

                /* Return ************************************************/
                return $fields_list;
            }

            /** @method is_table_type_valid *******************************
             * Checks if a given table type field is "valid" (exists as a
             * key to a table name in the saff_tables field).
             *-------------------------------------------------------------
             * @param string $table_type
             * @return bool
             *************************************************************/
            private function is_table_type_valid( $table_type ) {

                /* Definition ********************************************/
                $tables = array_keys( $this->tables );
                $table_type_valid = false;

                /* Processing ********************************************/
                foreach( $tables as $table_available ) {

                    if ( $table_available === $table_type ) {

                        $table_type_valid = true;
                        break;
                    }
                }
                
                return $table_type_valid;
            }
        }
    }
