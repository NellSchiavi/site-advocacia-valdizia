<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Redefinição de senha — Painel administrativo</title>
</head>
<body style="margin:0; padding:0; background-color:#F7F3EB; font-family: Arial, Helvetica, sans-serif; color:#14212B;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F7F3EB; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#FFFFFF; border:1px solid #E7DCCB; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#0F2D27; padding:20px 28px;">
                            <span style="color:#F7F3EB; font-size:16px; font-weight:bold;">Dra. Valdizia Melo</span><br>
                            <span style="color:#B08A55; font-size:12px; letter-spacing:0.05em; text-transform:uppercase;">Painel administrativo</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">Olá, {{ $nome }}.</p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                                Recebemos uma solicitação para redefinir a senha da sua conta no painel administrativo.
                            </p>

                            <p style="margin:0 0 24px; text-align:center;">
                                <a href="{{ $url }}" style="display:inline-block; background-color:#0F2D27; color:#F7F3EB; text-decoration:none; font-size:14px; font-weight:bold; padding:12px 24px; border-radius:999px;">
                                    Redefinir senha
                                </a>
                            </p>

                            <p style="margin:0 0 16px; font-size:13px; line-height:1.6; color:#6B6155;">
                                Este link expira em {{ $expiraEmMinutos }} minutos e só pode ser usado uma vez.
                            </p>

                            <p style="margin:0; font-size:13px; line-height:1.6; color:#6B6155;">
                                Se você não solicitou esta redefinição, ignore este e-mail — sua senha permanece inalterada.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
