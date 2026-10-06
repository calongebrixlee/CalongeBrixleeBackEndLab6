<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Products extends Controller {

    private $api;
    private $db;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->call->library('api');
        $this->db = $this->call->database();
    }

    public function index()
    {
        $this->api->require_jwt();

        $products = $this->db
            ->table('products')
            ->order_by('id', 'DESC')
            ->get_all();

        $this->api->respond(['data' => $products]);
    }

    public function show($id)
    {
        $this->api->require_jwt();

        $id = $this->valid_id($id);
        if ($id === false) {
            $this->api->respond_error('Invalid product ID', 400);
        }

        $product = $this->find_product($id);
        if (!$product) {
            $this->api->respond_error('Product not found', 404);
        }

        $this->api->respond(['data' => $product]);
    }

    public function create()
    {
        header('Access-Control-Expose-Headers: X-Product-Create-Handler, X-Product-Create-Step');
        header('X-Product-Create-Handler: products-create-v1');
        header('X-Product-Create-Step: authentication');
        $id = null;
        $step = 'authentication';

        try {
            $this->api->require_jwt();

            $step = 'validation';
            header('X-Product-Create-Step: ' . $step);
            $input = $this->read_input();
            $product = $this->validated_product($input);
            if (isset($product['errors'])) {
                $this->respond_validation_errors($product['errors']);
            }

            $step = 'insert';
            header('X-Product-Create-Step: ' . $step);
            $id = $this->db->table('products')->insert($product['data']);
            if (!is_numeric($id) || (int) $id < 1) {
                $this->api->respond_error('Product could not be saved. Please try again.', 500);
            }

            $step = 'confirmation';
            header('X-Product-Create-Step: ' . $step);
            $created = $this->find_product($id);
            if (!$created) {
                $created = ['id' => (int) $id] + $product['data'];
            }

            header('X-Product-Create-Step: complete');
            $this->api->respond([
                'data' => $created,
                'id'   => (int) $id,
            ], 201);
        } catch (Throwable $error) {
            error_log(sprintf(
                'Product create failed during %s (%s): %s',
                $step,
                get_class($error),
                $error->getMessage()
            ));

            if (is_numeric($id) && (int) $id > 0) {
                $this->api->respond([
                    'data' => ['id' => (int) $id] + ($product['data'] ?? []),
                    'id'   => (int) $id,
                ], 201);
            }

            $this->api->respond_error('Product could not be saved. Check the backend logs for details.', 500);
        }
    }

    public function update($id)
    {
        $this->save($id, false);
    }

    public function patch($id)
    {
        $this->save($id, true);
    }

    public function delete($id)
    {
        $this->api->require_jwt();

        $id = $this->valid_id($id);
        if ($id === false) {
            $this->api->respond_error('Invalid product ID', 400);
        }

        if (!$this->find_product($id)) {
            $this->api->respond_error('Product not found', 404);
        }

        $this->db->table('products')->where('id', $id)->delete();
        $this->api->respond(['message' => 'Product deleted']);
    }

    private function save($id, $partial)
    {
        $this->api->require_jwt();

        $id = $this->valid_id($id);
        if ($id === false) {
            $this->api->respond_error('Invalid product ID', 400);
        }

        if (!$this->find_product($id)) {
            $this->api->respond_error('Product not found', 404);
        }

        $input = $this->read_input();
        $product = $this->validated_product($input, $partial);
        if (isset($product['errors'])) {
            $this->respond_validation_errors($product['errors']);
        }

        $fields = $product['data'];
        $fields['updated_at'] = date('Y-m-d H:i:s');
        $this->db->table('products')->where('id', $id)->update($fields);

        $this->api->respond(['data' => $this->find_product($id)]);
    }

    private function read_input()
    {
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($content_type, 'application/json') !== false) {
            $input = $this->request->json();
            if (!is_array($input)) {
                $this->api->respond_error('Request body must be a valid JSON object', 400);
            }
            return $input;
        }

        switch ($_SERVER['REQUEST_METHOD'] ?? '') {
            case 'POST':
                return $this->request->post();
            case 'PUT':
                return $this->request->put();
            case 'PATCH':
                return $this->request->patch();
            default:
                $this->api->respond_error('Unsupported request method', 405);
        }
    }

    private function validated_product($input, $partial = false)
    {
        $allowed = ['name', 'description', 'price', 'stock'];
        $fields = array_intersect_key($input, array_flip($allowed));
        $errors = [];

        if (!$partial || array_key_exists('name', $fields)) {
            if (!isset($fields['name']) || !is_string($fields['name']) || trim($fields['name']) === '') {
                $errors['name'] = 'A non-empty name is required.';
            } elseif (strlen(trim($fields['name'])) > 255) {
                $errors['name'] = 'Name must be 255 characters or fewer.';
            } else {
                $fields['name'] = trim($fields['name']);
            }
        }

        if (!$partial || array_key_exists('price', $fields)) {
            if (!isset($fields['price']) || !is_numeric($fields['price']) || !is_finite((float) $fields['price']) || (float) $fields['price'] < 0 || (float) $fields['price'] > 99999999.99) {
                $errors['price'] = 'Price must be between 0 and 99999999.99.';
            } else {
                $fields['price'] = (float) $fields['price'];
            }
        }

        if (array_key_exists('description', $fields) && $fields['description'] !== null && !is_string($fields['description'])) {
            $errors['description'] = 'Description must be a string or null.';
        } elseif (!$partial && !array_key_exists('description', $fields)) {
            $fields['description'] = null;
        }

        if (array_key_exists('stock', $fields)) {
            $stock = filter_var($fields['stock'], FILTER_VALIDATE_INT);
            if ($stock === false || $stock < 0) {
                $errors['stock'] = 'Stock must be a non-negative integer.';
            } else {
                $fields['stock'] = $stock;
            }
        } elseif (!$partial) {
            $fields['stock'] = 0;
        }

        if ($partial && empty($fields)) {
            $errors['product'] = 'Provide at least one product field to update.';
        }

        return $errors ? ['errors' => $errors] : ['data' => $fields];
    }

    private function find_product($id)
    {
        return $this->db->table('products')->where('id', $id)->get();
    }

    private function valid_id($id)
    {
        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
            return false;
        }

        return (int) $id;
    }

    private function respond_validation_errors($errors)
    {
        $this->api->respond([
            'error'   => 'Validation failed',
            'details' => $errors,
            'status'  => 422,
        ], 422);
    }
}
