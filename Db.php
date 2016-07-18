<?php
namespace NonsenseCreativity;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * The wrapper class to help WordPress CRUD for a single table.
 *
 * @since      0.0.1
 * @package    NonsenseCreativity
 * @subpackage NonsenseCreativity\Db
 * @author     Lucis Lynn <nonsensecreativity@gmail.com>
 *
 * How To Use
 * 
 * $mydb = new NonsenseCreativity\Db( 'mytable_without_wpdb_prefix' );
 * $all_data  = $mydb->get_all( $orderby = 'date', $order = 'ASC' );
 * $row_data  = $mydb->get_row( $column = 'id', $value = 102, $format = '%d', $output_type = OBJECT, $offset = 10 );
 * $columns   = $mydb->get_columns();
 * $get_by    = $mydb->get_by( 
 *                          $columns     = array( 'id', 'slug' ),
 *                          $field       = 'id',
 *                          $field_value = 102,
 *                          $operator    = '=',
 *                          $format      = '%d',
 *                          $orderby     = 'slug',
 *                          $order       = 'ASC',
 *                          $output_type = OBJECT_K 
 *                      );
 * $get_wheres = $mydb->get_wheres( 
 *                          $column      = '*', 
 *                          $conditions  = array( 
 *                                             'category' => $category, 
 *                                             'id'     => $id 
 *                                        ),
 *                          $operator    = '=',
 *                          $format      = array( 
 *                                              'category' => '%s',
 *                                              'id' => '%d' 
 *                                        ),
 *                          $orderby     = 'category',
 *                          $order       = 'ASC',
 *                          $output_type = OBJECT_K
 *                      );
 * $insert_id = $mydb->insert( $data = array( 'title' => 'text', 'date' => date("Y-m-d H:i:s") ) );
 */
class Db {

    /**
     * The current table name
     *
     * @var string
     */
    protected $tablename = null;

    /**
     * @var string
     */
    protected $prefix = null;

    /**
     * @var string 
     */
    protected $charset = null;

    /**
     * @var object 
     */
    protected $wpdb = null;

    public function __call( $method, $arguments ) {

        if( method_exists( $this, $method ) ) {
            if( $this->table_exists() ) {
                return call_user_func_array( array( $this, $method ), $arguments );
            } else {
                throw new \Exception( sprintf( "Table for %s Not Exists in the Database", $this->tablename ), 1 );
            }
        }
    }

    /**
     * Constructor for the class to inject the table name
     * 
     * @since 0.0.1
     * @param String    $tablename   The table name
     */
    public function __construct( $tablename ) {

        global $wpdb;
        
        if( is_null( $this->wpdb ) ) {
            $this->wpdb    = $wpdb;
            $this->prefix  = $wpdb->prefix;
            $this->charset = $wpdb->get_charset_collate();
        }

        $prefix_len = strlen( $this->prefix );

        if( $prefix_len > 0 ) {

            if( substr( $tablename, 0, $prefix_len ) === $this->prefix ) {
                $this->tablename = $tablename;
            } else {
                $this->tablename = $this->prefix . $tablename;
            }

        } else {
            $this->tablename = $tablename;
        }
    }

    /**
     * Check if the specified table exists in database
     * 
     * @since  0.0.1
     * @return boolean
     */
    public function table_exists() {
        if( $this->wpdb->get_var( $this->wpdb->prepare( "SHOW TABLES like %s", $this->tablename ) ) !== $this->tablename ) {
            return false;
        }
        return true;
    }

    /**
     * Get all from the selected table
     *
     * @since  0.0.1     
     * @param  string    $orderby    The column for ordering base
     * @param  string    $order      Order key eq. ASC or DESC
     * @return array     $results    The query results
     */
    public function get_all( $orderby = NULL, $order = NULL, $output_type = OBJECT ) {

        $sql = "SELECT * FROM $this->tablename";

        if( null !== $orderby ) {

            $orderby = $this->check_column( $orderby );
            $order   = null !== $order ? $this->check_order( $order ) : null;

            if( $orderby ) {
                $sql .= " ORDER BY $orderby";
                if( $order ) {
                    $sql .= " $order";
                }
            }
        }

        $results = $this->wpdb->get_results( $sql, $output_type );
        return $results;
    }

    /**
     * Get all data of single row from a column that contain specific data
     *
     * @since 0.0.1
     * @param string           $column         The column name
     * @param string           $value          the value to check for
     * @param string           $format         $wpdb->prepare() string format
     * @param constant         $output_type    One of three pre-defined constants. Defaults to OBJECT.
     * @param int              $row_offset     The desired row (0 being the first). Defaults to 0. 
     * @return object|array    $results        Query results
     * 
     */
    public function get_row( $column, $value, $format, $output_type = OBJECT, $row_offset = 0 ) {
        $format  = $this->check_format( $format );
        $column  = $this->check_column( $column );

        if( ! $column || ! $format ) {
            return;
        }

        $sql     = $this->wpdb->prepare( "SELECT * FROM $this->tablename WHERE `$column` = $format", $value );
        $results = $this->wpdb->get_row( $sql, $output_type, $row_offset );

        return $results;
    }

    /**
     * Get list of columns available in $this->tablename;
     * @since  0.0.1
     * @return array of column names
     */
    public function get_columns() {
        return $this->wpdb->get_col( "DESCRIBE $this->tablename", 0 );
    }

    /**
     * Get a value by a single condition
     *
     * @since  0.0.1
     * @param  string|array    $column         List of column to be returned.
     * @param  string          $field          The column name used in WHERE clause as the query condition
     * @param  string|array    $value          The column value used for the condition expression in WHERE clause.
     *                                         If WHERE clause operator used IN, BETWEEN, etc. this require multiple values specified in array.
     * @param  string          $operator       The condition expression operator in WHERE clause
     * @param  string          $format         The data format for $value
     * @param  string          $orderby        The column for ordering base
     * @param  string          $order          Order key eq. ASC or DESC
     * @param  const           $output_type    Type constant OBJECT|ARRAY_A|ARRAY_N
     * @return array           $result
     */
    public function get_by( $column, $field, $value, $operator = '=', $format = '%s', $orderby = NULL, $order = 'ASC', $output_type = OBJECT ) {

        $order    = $this->check_order( $order );
        $operator = $this->check_operator( $operator );
        $format   = $this->check_format( $format );
        $column   = $this->check_column( $column );

        $sql = "SELECT $column FROM $this->tablename WHERE";

        $method = 'sql_' . strtolower( str_replace( ' ', '_', $operator ) );

        if( method_exists( $this, $method ) ) {
            $sql .= call_user_func( array( $this, $method ), $field, $value, $format, false );
        } else {
            $sql .= $this->sql_default( $field, $value, $operator, $format, false );
        }

        $result = $this->wpdb->get_results( $sql, $output_type );

        return $result;
    }

    /**
     * Get a value by multiple conditions, operators and formats
     *
     * @since  0.0.1
     * @param  string|array    $column         List of column to be returned.
     * @param  array           $conditions     Set of conditions to used in WHERE clause
     * @param  string          $field          The column name used in WHERE clause as the query condition
     * @param  string          $value          The column value used for the condition expression in WHERE clause
     * @param  string|array    $operator       The condition expression operator in WHERE clause.
     *                                         If it's string it will be used to all condition fields.
     *                                         Used array key value pair for defining different operator for each of the $conditions key.
     * @param  string|array    $format         The data format for $value
     *                                         If it's string it will be used to all condition fields value.
     *                                         Used array key value pair for defining different format for each of the $conditions value.
     * @param  string          $orderby        The column for ordering base
     * @param  string          $order          Order key eq. ASC or DESC
     * @param  const           $output_type    Type constant OBJECT|ARRAY_A|ARRAY_N
     * @return array           $result
     */
    public function get_wheres( $column = '', Array $conditions, $operator = '=', $format = '%s', $orderby = NULL, $order = 'ASC', $output_type = OBJECT ) {

        $order    = $this->check_order( $order );
        $operator = $this->check_operator( $operator );
        $format   = $this->check_format( $format );
        $column   = $this->check_column( $column );

        $sql = "SELECT $column FROM $this->tablename WHERE 1=1";

        $i = 0;

        foreach ( $conditions as $field => $value ) {

            if( !$value ) {
                $i++;
                continue;
            }

            if( is_array( $operator ) ) {
                if( isset( $operator[$field] ) ) {
                    $op = $operator[$field];
                } else if( isset( $operator[$i] ) ) {
                    $op = $operator[$i];
                } else {
                    $op = '=';
                }
            } else {
                $op = $operator;
            }

            if( is_array( $format ) ) {
                if( isset( $format[$field] ) ) {
                    $f = $format[$field];
                } else if( isset( $format[$i] ) ) {
                    $f = $format[$i];
                } else {
                    $f = '%s';
                }
            } else {
                $f = $format;
            }

            $method = 'sql_' . strtolower( str_replace( ' ', '_', $op ) );

            if( method_exists( $this, $method ) ) {
                $sql .= call_user_func( array( $this, $method ), $field, $value, $f, true );
            } else {
                $sql .= $this->sql_default( $field, $value, $op, $f, true );
            }

            $i++;
        }

        $result = $this->wpdb->get_results( $sql, $output_type );
        return $result;
    }

    /**
     * Count a table record in the table
     *
     * @since  0.0.1
     * @param  int $column_offset
     * @param  int $row_offset
     * @return int number of the count
     */
    public function count( $column_offset = 0, $row_offset = 0 ) {
        $sql = "SELECT COUNT(*) FROM $this->tablename";
        return $this->wpdb->get_var( $sql, $column_offset, $row_offset );
    }

    /**
     * count a record in the column
     *
     * @since  0.0.1
     * @param  string $column   Column name in table
     * @return array  $returns  Array set of counts per column
     */
    public function count_column( $column ) {
        $output_type = ARRAY_A;
        $column      = $this->check_column( $column );

        $sql    = "SELECT $column, COUNT(*) AS count FROM $this->tablename GROUP BY $column";

        $totals = $this->wpdb->get_results( $sql, $output_type );

        $returns = array();
        $all = 0;

        foreach ( $totals as $row ) {
            $all = $all + $row['count'];
            $returns[$row[$column]] = $row['count'];
        }

        $returns['all'] = $all;

        return $returns;
    }

    /**
     * Insert data into the current data
     *
     * @since  0.0.1
     * @param  array  $data array( 'column' => 'values' ) - Data to enter into the database table
     * @return int    The row ID
     * 
     */
    public function insert( Array $data ) {
        if( empty( $data ) ) {
            return false;
        }

        $this->wpdb->insert( $this->tablename, $data );
        return $this->wpdb->insert_id;
    }

    /**
     * Update a table record in the database
     *
     * @since  0.0.1
     * @param  array       $data       A named array of WHERE clauses (in column => value pairs).
     *                                 Multiple clauses will be joined with ANDs. Both $where columns and $where values should be "raw". 
     * @param  array       $condition  Key value pair for the where clause of the query.
     *                                 Multiple clauses will be joined with ANDs. Both $where columns and $where values should be "raw". 
     * @return int|boolean $updated    This method returns the number of rows updated, or false if there is an error.
     */
    public function update( Array $data, Array $condition ) {
        if( empty( $data ) ) {
            return false;
        }

        $updated = $this->wpdb->update( $this->tablename, $data, $condition );
        return $updated;
    }

    /**
     * Delete row on the database table
     * 
     * @since  0.0.1
     * @param  array  $conditionValue - Key value pair for the where clause of the query
     * @return int Num rows deleted
     */
    public function delete( Array $condition ) {
        $deleted = $this->wpdb->delete( $this->tablename, $condition );
        return $deleted;
    }

    /**
     * Delete rows on the database table
     * 
     * @since  0.0.1
     * @param  string   $field            The table column name
     * @param  array    $conditionvalue   The value to be deleted
     * @param  string   $format           $wpdb->prepare() Format String
     * @return $deleted
     * 
     */
    public function bulk_delete( $field, array $conditionvalue, $format = '%s' ) {

        $format = $this->check_format( $format );

        // how many entries will we select?
        $how_many = count( $conditionvalue );

        // prepare the right amount of placeholders
        // if you're looing for strings, use '%s' instead
        $placeholders = array_fill( 0, $how_many, $format );

        // glue together all the placeholders...
        // $format = '%s, %s, %s, %s, %s, [...]'
        $format = implode( ', ', $placeholders );

        $sql = "DELETE FROM $this->tablename WHERE $field IN ($format)";
        $sql = $this->wpdb->prepare( $sql, $conditionvalue );

        $deleted = $this->wpdb->query( $sql );

        return $deleted;
    }

    /**
     * Get supported operands
     * 
     * @since  0.0.1
     * @return array    List of all supported operands
     * 
     */
    protected function get_operands() {
        return apply_filters( __METHOD__, 
                array( 
                        '=',
                        '!=',
                        '>',
                        '<',
                        '>=',
                        '<=',
                        '<=>',
                        'like',
                        'not like',
                        'in',
                        'not in',
                        'between',
                        'not between',
                    )
                );
    }

    /**
     * check/sanitize column parameter to make sure the column is available in $this->tablename.
     *
     * @return array|string    Return the Array of sanitized columns or string of commas separated column name.
     */
    protected function check_column( $columns, $return = 'string' ) {
        if( is_array( $columns ) ) {
            foreach( $columns as $key => $value ) {
                if( !in_array( $value, $this->get_columns() ) ) {
                    unset( $columns[$key] );
                }
            }

            if( !empty( $columns ) ) {
                if( $return == 'string' ) {
                    return implode( ',', $columns );
                } else {
                    return $columns;
                }
            } else {
                return '*';
            }

        } else {
            if( $columns === '*' ) {
                return $columns;
            }
            if( in_array( $columns, $this->get_columns() ) ) {
                return $columns;
            } else {
                return '*';
            }
        }
       
    }

    /**
     * check/sanitize ORDER string
     * 
     * @since  0.0.1
     * @return string    order string ASC|DESC
     * 
     */
    protected function check_order( $order = 'ASC' ) {
        if( is_null( $order ) ) {
            return 'ASC';
        } else {
            $order = in_array( $order, array( 'ASC', 'DESC' ) ) ? $order : 'ASC';
            return $order;
        }
    }

    /**
     * check/sanitize operator string.
     * 
     * @since  0.0.1
     * @param  string|array    Array of operators or single operator string to be check.
     * @return string|array    The operator that pass the check.
     * @uses   Bolts\Core\Libraries\Db::get_operands()
     * 
     */
    protected function check_operator( $operator ) {

        $operators = array();

        if( is_array( $operator ) ) {
            foreach( $operator as $k => $op ) {
                $operators[$k] = $this->check_operator( $op );
            }
            return $operators;
        } else {
            $operator = ( in_array( $operator, $this->get_operands() ) ? strtoupper( $operator ) : '=' );
            return $operator;
        }
    }

    /**
     * check/sanitize format string
     * 
     * @since  0.0.1
     * @param  string|array    The array of formats or single format string need to be check.
     * @return string|array    The Array of checked formats or single checked format string.
     * 
     */
    protected function check_format( $format ) {
        $formats = array();
        if( is_array( $format ) ) {
            foreach( $format as $k => $f ) {
                $formats[$k] = $this->check_format( $f );
            }
            return $formats;
        } else {
            $format = ( in_array( $format, array( '%s', '%d', '%f' ) ) ? $format : '%s' );
            return $format;
        }
    }

    /**
     * Append IN clause for sql query via $wpdb->prepare
     * 
     * @since  0.0.1
     * @param  string    $column    The Column Name
     * @param  array     $value     The array values for the WHERE clause    
     * @param  string    $format    Single format string for prepare.
     * @param  boolean   $and       before the statement prepend AND if true, prepend OR if $and === 'OR', prepend nothing if false
     * @return string    $sql       The prepared sql statement
     * 
     */
    protected function sql_in( $column, Array $value, $format = '%s', $and = true ) {

        $how_many     = count( $value );
        $placeholders = array_fill( 0, $how_many, $format );
        $new_format   = implode( ', ', $placeholders );

        $sql  = $this->sql_and( $and );
        $sql .= " `$column` IN ($new_format)";
        $sql  = $this->wpdb->prepare( $sql, $value );

        return $sql;
    }

    /**
     * Append NOT IN clause for sql query via $wpdb->prepare
     * 
     * @since  0.0.1
     * @param  string    $column    The Column Name
     * @param  array     $value     The array values for the WHERE clause    
     * @param  string    $format    Single format string for prepare.
     * @param  boolean   $and       before the statement prepend AND if true, prepend OR if $and === 'OR', prepend nothing if false
     * @return string    $sql       The prepared sql statement
     * 
     */
    protected function sql_not_in( $column, Array $value, $format = '%s', $and = true ) {

        $how_many     = count( $value );
        $placeholders = array_fill( 0, $how_many, $format );
        $new_format   = implode( ', ', $placeholders );

        $sql  = $this->sql_and( $and );
        $sql .= " `$column` NOT IN ($new_format)";
        $sql  = $this->wpdb->prepare( $sql, $value );

        return $sql;
    }

    /**
     * Append BETWEEN clause for sql query via $wpdb->prepare
     * 
     * @since  0.0.1
     * @param  string    $column    The Column Name
     * @param  array     $value     The array values for the WHERE clause    
     * @param  string    $format    Single format string for prepare.
     * @param  boolean   $and       before the statement prepend AND if true, prepend OR if $and === 'OR', prepend nothing if false
     * @return string    $sql       The prepared sql statement
     * 
     */
    protected function sql_between( $column, Array $value, $format = '%s', $and = true ) {
        if( count( $value ) < 2 ) {
            throw new \Exception( 'Values for BETWEEN query must be more than one.', 1 );
        }

        $sql  = $this->sql_and( $and );
        $sql .= $this->wpdb->prepare( " `$column` BETWEEN $format AND $format", $value[0], $value[1] );

        return $sql;
    }

    /**
     * Append NOT BETWEEN clause for sql query via $wpdb->prepare
     * 
     * @since  0.0.1
     * @param  string    $column    The Column Name
     * @param  array     $value     The array values for the WHERE clause    
     * @param  string    $format    Single format string for prepare.
     * @param  boolean   $and       before the statement prepend AND if true, prepend OR if $and === 'OR', prepend nothing if false
     * @return string    $sql       The prepared sql statement
     * 
     */
    protected function sql_not_between( $column, Array $value, $format = '%s', $and = true ) {
        if( count( $value ) < 2 ) {
            throw new \Exception( 'Values for NOT BETWEEN query must be more than one.', 1 );
        }

        $sql = $this->sql_and( $and );
        $sql .= $this->wpdb->prepare( " `$column` NOT BETWEEN $format AND $format", $value[0], $value[1] );

        return $sql;
    }

    /**
     * Append LIKE clause for sql query via $wpdb->prepare
     * 
     * @since  0.0.1
     * @param  string    $column    The Column Name
     * @param  string    $value     The LIKE string values for the WHERE clause    
     * @param  string    $format    Single format string for prepare.
     * @param  boolean   $and       before the statement prepend AND if true, prepend OR if $and === 'OR', prepend nothing if false
     * @return string    $sql       The prepared sql statement
     * 
     */
    protected function sql_like( $column, $value, $format = '%s', $and = true ) {
        $sql = $this->sql_and( $and );
        $sql .= $this->wpdb->prepare( " `$column` LIKE $format", $value );
        return $sql;
    }

    /**
     * Append NOT LIKE clause for sql query via $wpdb->prepare
     * 
     * @since  0.0.1
     * @param  string    $column    The Column Name
     * @param  string    $value     The LIKE string values for the WHERE clause    
     * @param  string    $format    Single format string for prepare.
     * @param  boolean   $and       before the statement prepend AND if true, prepend OR if $and === 'OR', prepend nothing if false
     * @return string    $sql       The prepared sql statement
     * 
     */
    protected function sql_not_like( $column, $value, $format = '%s', $and = true ) {
        $sql = $this->sql_and( $and );
        $sql .= $this->wpdb->prepare( " `$column` NOT LIKE $format", $value );
        return $sql;
    }

    /**
     * Append based on operator expression in WHERE clause for sql query via $wpdb->prepare
     * 
     * @since  0.0.1
     * @param  string    $column    The Column Name
     * @param  string    $value     The string values for the WHERE clause  
     * @param  string    $op        The string operator for the WHERE clause. eq:=, !=, etc
     * @param  string    $format    Single format string for prepare.
     * @param  boolean   $and       before the statement prepend AND if true, prepend OR if $and === 'OR', prepend nothing if false
     * @return string    $sql       The prepared sql statement
     * 
     */
    protected function sql_default( $column, $value, $op, $format = '%s', $and = true ) {
        $sql = $this->sql_and( $and );
        $sql .= $this->wpdb->prepare( " `$column` $op $format", $value );
        return $sql;
    }


    /**
     * get AND|OR|(empty) based on parameter
     * 
     * @since  0.0.1
     * @param  boolean   $and   AND if true, prepend OR if $and === 'OR', prepend nothing if false
     * @return string
     * 
     */
    protected function sql_and( $and = true ) {

        if( $and === true ) {
            return " AND";
        } else if( strtoupper( $and ) === 'OR' ) {
            return " OR";
        }

        return '';
    }

}