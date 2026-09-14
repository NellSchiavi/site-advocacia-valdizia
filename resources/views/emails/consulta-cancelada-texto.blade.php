@php
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $data = $appointment->data;
    $dataFormatada = "{$data->day} de {$meses[$data->month - 1]} de {$data->year}";
@endphp
Olá, {{ $appointment->nome_cliente }}.

Sua consulta foi cancelada.

Área: {{ $appointment->areaAtuacao->nome }}
Assunto: {{ $appointment->assunto }}
Data: {{ $dataFormatada }}
Horário: {{ substr($appointment->hora_inicio, 0, 5) }} às {{ substr($appointment->hora_fim, 0, 5) }}

Entre em contato com o escritório para realizar um novo agendamento.

Atenciosamente,
Dra. Valdizia Melo
Advocacia e Assessoria Jurídica
