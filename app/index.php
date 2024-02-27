<?php

include_once "router.php";

$router = new Router();

$router->add_route('GET', '/clientes/:id/extrato', function ($id) {
    $conn = pg_connect("host=db port=5432 dbname=rinha user=admin password=123");

    pg_query($conn, "BEGIN");

    $get_client = pg_query($conn, "SELECT saldo, limite, (SELECT count(*) FROM transacoes) AS quantidade FROM clientes WHERE id = $id");

    if (pg_num_rows($get_client) < 1) {
        http_response_code(404);
        exit;
    }

    $obj_client = pg_fetch_object($get_client);

    if (!$obj_client->quantidade > 10) {
        pg_query($conn, "CALL gerencia_transacoes($id)");
    }

    $get_extract = pg_query(
        $conn,
        "SELECT valor, tipo, descricao, realizada_em
        FROM transacoes
        WHERE cliente_id = $id
        ORDER by id DESC LIMIT 10");

    pg_query($conn, "COMMIT");

    $arr_transactions = pg_fetch_all($get_extract);

    $arr_format = [];

    for ($i = 0; $i < count($arr_transactions); $i++) {
        array_push($arr_format, [
            'valor' => intval($arr_transactions[$i]['valor']),
            'tipo' => $arr_transactions[$i]['tipo'],
            'descricao' => $arr_transactions[$i]['descricao'],
            'realizada_em' => date(DATE_ATOM, strtotime($arr_transactions[$i]['realizada_em'])),
        ]);
    }

    echo json_encode([
        'saldo' => [
            'total' => intval($obj_client->saldo),
            'data_extrato' => date(DATE_ATOM),
            'limite' => intval($obj_client->limite),
        ],
        'ultimas_transacoes' => $arr_format,
    ]);
});

$router->add_route('POST', '/clientes/:id/transacoes', function ($id) {
    $data = json_decode(file_get_contents('php://input'), true);

    $valid_values = [
        'valor',
        'tipo',
        'descricao'
    ];

    for ($i = 0; $i < count($valid_values); $i++) {
        if (!array_key_exists($valid_values[$i], $data)) {
            echo json_encode("field '$valid_values[$i]' is mandatory");
            http_response_code(422);
            exit;
        }
    }

    if (empty($data['valor']) || empty($data['tipo']) || empty($data['descricao'])) {
        echo json_encode("fields cannot be empty");
        http_response_code(422);
        exit;
    }

    $value = $data['valor'];
    $type = $data['tipo'];
    $description = $data['descricao'];

    if ($value <= 0  || !is_int($value)) {
        echo json_encode("field 'valor' must be integer");
        http_response_code(422);
        exit;
    }

    if (!in_array($type, ['c', 'd'])) {
        echo json_encode("value of field 'tipo' must be 'c' or 'd'");
        http_response_code(422);
        exit;
    }

    if (strlen($description) > 10) {
        echo json_encode("value of 'descricao' must not exceed 10 characters");
        http_response_code(422);
        exit;
    }

    $conn = pg_connect("host=db port=5432 dbname=rinha user=admin password=123");

    pg_query($conn, "BEGIN");

    $get_client = pg_query($conn, "SELECT * FROM clientes WHERE id = $id FOR UPDATE");

    if (pg_num_rows($get_client) < 1) {
        pg_query($conn, "ROLLBACK");
        echo json_encode("user not found");
        http_response_code(404);
        exit;
    }

    $obj_client = pg_fetch_object($get_client);

    $newValue = 0;
    $query_transacoes = '';

    if ($type == 'c') {
        $newValue = $obj_client->saldo + $value;
    } else {
        if (($obj_client->saldo + $obj_client->limite - $value) < 0) {
            pg_query($conn, "ROLLBACK");
            echo json_encode("limit exceeded");
            http_response_code(422);
            exit;
        }

        $newValue = $obj_client->saldo - $value;
    }

    $query_transacoes = "UPDATE clientes SET saldo = $newValue WHERE id = $id;
        INSERT INTO transacoes (cliente_id, valor, tipo, descricao)
        VALUES ($id, $value, '$type', '$description');";

    pg_query($conn, $query_transacoes);

    pg_query($conn, "COMMIT");

    echo json_encode(
        [
            'limite' => $obj_client->limite,
            'saldo' => $newValue,
        ],
    );
});

$router->match_route();
