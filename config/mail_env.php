<?php
// SMTP environment bootstrap for PHPMailer/email 2FA
// Fill these values with your SMTP provider details

// Example: Gmail SMTP (use an App Password)
putenv('SMTP_HOST=smtp.gmail.com');
putenv('SMTP_PORT=587');
putenv('SMTP_USER=jeyceebaya@gmail.com');
putenv('SMTP_PASS=qqaq vtkr juid dske');
putenv('SMTP_FROM=jeyceebaya@gmail.com');
putenv('SMTP_FROM_NAME=Alertara');

$aiKey = getenv('OPENAI_API_KEY') ?: getenv('NLP_AI_API_KEY') ?: getenv('CLOUD_NLP_API_KEY') ?: '';
putenv('OPENAI_API_KEY=' . $aiKey);
putenv('NLP_AI_API_KEY=' . (getenv('NLP_AI_API_KEY') ?: $aiKey));
putenv('CLOUD_NLP_API_KEY=' . (getenv('CLOUD_NLP_API_KEY') ?: $aiKey));

// If you use a different provider, update accordingly, e.g.:
// putenv('SMTP_HOST=smtp.mailgun.org');
// putenv('SMTP_PORT=587');
// putenv('SMTP_USER=postmaster@sandboxXXXX.mailgun.org');
// putenv('SMTP_PASS=your_mailgun_smtp_password');
// putenv('SMTP_FROM=noreply@yourdomain.com');
?>


