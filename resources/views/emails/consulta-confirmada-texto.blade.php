@php
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $data = $appointment->data;
    $dataFormatada = "{$data->day} de {$meses[$data->month - 1]} de {$data->year}";
@endphp
Olá, {{ $appointment->nome_cliente }}.

Sua consulta foi confirmada. Seguem os detalhes:

Área: {{ $appointment->areaAtuacao->nome_publico }}
Assunto: {{ $appointment->assunto }}
Data: {{ $dataFormatada }}
Horário: {{ substr($appointment->hora_inicio, 0, 5) }} às {{ substr($appointment->hora_fim, 0, 5) }}

Caso tenha alguma dúvida ou precise entrar em contato antes da consulta, fale com o escritório.

Atenciosamente,
Dra. Valdizia Melo
Advocacia e Assessoria Jurídica
