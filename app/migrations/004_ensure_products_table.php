<?php
require_once __DIR__ . '/003_create_products_table.php';

class Ensure_products_table {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        (new Create_products_table())->create_products_table();
    }

    public function down()
    {
        $this->_lava->dbforge->drop_table('products');
    }
}
