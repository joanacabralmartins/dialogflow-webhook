<?php
// Captura os dados da solicitação POST em JSON
$inputJSON = file_get_contents('php://input');
$requestData = json_decode($inputJSON, true);

// Verifica se os dados foram recebidos corretamente
if (!$requestData) {
    http_response_code(400);
    die('Erro: Nenhum dado recebido.');
}

$intent = $requestData['queryResult']['intent']['displayName'];
$parameters = $requestData['queryResult']['parameters'];

switch ($intent) {
    case 'cadastro.aluno':
        $aluno_data = [
            'nome' => $parameters['nome'],
            'cpf' => $parameters['cpf'],
            'matricula' => $parameters['matricula'],
        ];

        $url = 'http://127.0.0.1:8000/api/chatbot/alunos';
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($aluno_data),
            ],
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        // Verifica se a resposta da API contém erros de validação
        $responseData = json_decode($response, true);
        if (isset($responseData['success']) && $responseData['success'] === false) {
            // A resposta da API contém erros de validação
            $validationErrors = array_map(function ($error) {
                return implode(', ', $error);
            }, $responseData['data']);
        
            $fulfillmentText = 'Erros de validação: ' . implode(', ', $validationErrors) 
                . ' Vamos tentar novamente?';
        } else {
            $fulfillmentText = 'Cadastro realizado!';
        }
        break;

    case 'status.atestado - busca.cpf':
        $cpfAluno = $parameters['cpf'];
        $url = "http://127.0.0.1:8000/api/chatbot/atestados/{$cpfAluno}";
        $response = file_get_contents($url);

        if ($response) {
            $status_atestado = 'Status do atestado: ' . json_decode($response, true);
            $fulfillmentText = $status_atestado;
        } else {
            $fulfillmentText = 'Desculpe, ocorreu um erro ao verificar o status do atestado.';
        }
        break;

    case 'enviar.atestado':
        $alunoId = $parameters['alunoId'];
        $atestado_data = [
            'descricao' => $parameters['descricao'],
        ];

        $url = 'http://127.0.0.1:8000/api/chatbot/atestados';
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($atestado_data),
            ],
        ];
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response) {
            $fulfillmentText = 'Atestado enviado com sucesso.';
        } else {
            $fulfillmentText = 'Desculpe, ocorreu um erro ao enviar o atestado.';
        }
        break;

    default:
        $fulfillmentText = 'Desculpe, não entendi a sua solicitação.';
        break;
}

$responseData = ['fulfillmentText' => $fulfillmentText];
header('Content-Type: application/json');
echo json_encode($responseData);