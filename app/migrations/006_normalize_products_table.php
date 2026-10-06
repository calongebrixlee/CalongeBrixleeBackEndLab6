<?php
require_once __DIR__ . '/003_create_products_table.php';

class Normalize_products_table {

    private $_lava;
    protected $dbforge;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        if (!$this->_lava->dbforge->table_exists('products')) {
            (new Create_products_table())->create_products_table();
            return;
        }

        $columns = [
            'id' => [
                'aliases' => ['product_id'],
                'definition' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                    'null' => false,
                ],
            ],
            'name' => [
                'aliases' => ['product_name', 'item_name', 'title'],
                'definition' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
            ],
            'description' => [
                'aliases' => ['product_description', 'details'],
                'definition' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
            ],
            'price' => [
                'aliases' => ['product_price', 'selling_price'],
                'definition' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'null' => false,
                    'default' => 0,
                ],
            ],
            'stock' => [
                'aliases' => ['product_quantity', 'quantity', 'qty'],
                'definition' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => false,
                    'default' => 0,
                ],
            ],
        ];

        foreach ($columns as $column => $settings) {
            if ($this->column_exists($column)) {
                continue;
            }

            $renamed = false;
            foreach ($settings['aliases'] as $alias) {
                if (!$this->column_exists($alias)) {
                    continue;
                }

                $this->_lava->db->raw(
                    "ALTER TABLE `products` RENAME COLUMN `{$alias}` TO `{$column}`"
                );
                $renamed = true;
                break;
            }

            if (!$renamed) {
                if ($column === 'id') {
                    throw new RuntimeException('The products table must have an id or product_id column.');
                }

                $this->_lava->dbforge->add_column('products', [
                    $column => $settings['definition'],
                ]);
            }
        }
    }

    private function column_exists($column)
    {
        $driver = strtolower(database_config()['main']['driver'] ?? 'mysql');
        if ($driver === 'sqlite') {
            $stmt = $this->_lava->db->raw('PRAGMA table_info(`products`)');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $field) {
                if ($field['name'] === $column) {
                    return true;
                }
            }
            return false;
        }

        return $this->_lava->dbforge->column_exists('products', $column);
    }

    public function down()
    {
        // Keep the normalized columns so existing product data remains usable.
    }
}
