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

            $fulfillmentText = 'Erros de validação: ' . implode(', ', $validationErrors) . ' Vamos tentar novamente?';
        } else {
            $fulfillmentText = 'Cadastro realizado!';
        }
        break;

    case 'status.atestado - busca.cpf':
        $cpfAluno = $parameters['cpf'];
        $url = "http://127.0.0.1:8000/api/chatbot/atestados/{$cpfAluno}";
        $response = file_get_contents($url);

        if ($response) {
            $atestados = json_decode($response, true);

            if (!empty($atestados)) {
                $fulfillmentText = 'Informações do(s) seu(s) atestado(s):';

                foreach ($atestados as $atestado) {
                    $motivo = $atestado['motivo'];
                    $status = $atestado['status'];

                    $fulfillmentText .= " Motivo: $motivo, Status: $status;";
                }
            } else {
                $fulfillmentText = 'Não foram encontrados atestados para o CPF informado.';
            }
        } else {
            $fulfillmentText = 'Desculpe, ocorreu um erro ao verificar o status dos atestados.';
        }
        break;

    case 'Whatsapp Media - fallback':
        $receivedData = json_encode($requestData, JSON_PRETTY_PRINT);

        // Especifique o caminho para o arquivo de log
        $logFilePath = 'log.txt';

        // Abra o arquivo de log em modo de escrita (append)
        if ($fileHandle = fopen($logFilePath, 'a')) {
            // Formate a mensagem de log com data e hora
            $logMessage = '[' . date('Y-m-d H:i:s') . "] Informações recebidas:\n" . $receivedData . "\n\n";

            // Escreva a mensagem no arquivo de log
            fwrite($fileHandle, $logMessage);

            // Feche o arquivo de log
            fclose($fileHandle);
        } else {
            // Se não for possível abrir o arquivo de log, você pode lidar com isso aqui
            $fulfillmentText = 'Desculpe, ocorreu um erro ao registrar as informações recebidas.';
        }

        // Defina uma resposta ou mensagem de confirmação
        $fulfillmentText = 'As informações recebidas foram registradas em um log.';
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
