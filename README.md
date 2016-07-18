# WPDB-Helper
Helper class to work with WordPress Database

# How To Use

##### Initiate table
`$mydb = new NonsenseCreativity\Db( 'mytable_without_wpdb_prefix' );`

##### Get all data

    $all_data  = $mydb->get_all( $orderby = 'date', $order = 'ASC' );
    
##### Get Row Data

    $row_data  = $mydb->get_row( 
                    $column = 'id', $value = 102, 
                    $format = '%d', 
                    $output_type = OBJECT, $offset = 10 
    );
    
##### Get Column Lists
    $columns   = $mydb->get_columns();

##### Get By Column with clause
    $get_by = $mydb->get_by( 
        $columns     = array( 'id', 'slug' ),`
        $field       = 'id',
        $field_value = 102,
        $operator    = '=',
        $format      = '%d',
        $orderby     = 'slug',
        $order       = 'ASC',
        $output_type = OBJECT_K 
    );

##### Get with Where clause

    $get_wheres = $mydb->get_wheres( 
        $column      = '*', 
        $conditions  = array( 
                            'category' => $category, 
                            'id'     => $id 
                        ),
        $operator    = '=',
        $format      = array( 
                            'category' => '%s',
                            'id' => '%d' 
                        ),
        $orderby     = 'category',
        $order       = 'ASC',
        $output_type = OBJECT_K
    );
    
##### Insert Data

    $insert_id = $mydb->insert( 
                    $data = array( 
                                'title' => 'text', 
                                'date' => date("Y-m-d H:i:s") 
                            )
    );