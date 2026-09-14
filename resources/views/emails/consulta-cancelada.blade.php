@php
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    $data = $appointment->data;
    $dataFormatada = "{$data->day} de {$meses[$data->month - 1]} de {$data->year}";
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Cancelamento de consulta — Dra. Valdizia Melo</title>
</head>
<body style="margin:0; padding:0; background-color:#F7F3EB; font-family: Arial, Helvetica, sans-serif; color:#14212B;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F7F3EB; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#FFFFFF; border:1px solid #E7DCCB; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#0F2D27; padding:20px 28px;">
                            <span style="color:#F7F3EB; font-size:16px; font-weight:bold;">Dra. Valdizia Melo</span><br>
                            <span style="color:#B08A55; font-size:12px; letter-spacing:0.05em; text-transform:uppercase;">Advocacia e Assessoria Jurídica</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">Olá, {{ $appointment->nome_cliente }}.</p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                                Informamos que sua consulta agendada para {{ $dataFormatada }}, das {{ substr($appointment->hora_inicio, 0, 5) }} às {{ substr($appointment->hora_fim, 0, 5) }}, precisou ser cancelada devido a uma indisponibilidade na agenda do escritório.
                            </p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                                Entre em contato com o escritório para realizar um novo agendamento.
                            </p>

                            <p style="margin:24px 0 0; font-size:15px; line-height:1.6;">
                                Atenciosamente,<br>
                                Dra. Valdizia Melo<br>
                                Advocacia e Assessoria Jurídica
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
