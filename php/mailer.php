<?php
/**
 * SMTP Mailer using PHPMailer
 *
 * SETUP INSTRUCTIONS:
 * 1. Download PHPMailer from: https://github.com/PHPMailer/PHPMailer
 *    Click Code > Download ZIP, extract it.
 * 2. Copy the /src folder into: nutriguideph/php/PHPMailer/src/
 *    So you should have:
 *      php/PHPMailer/src/PHPMailer.php
 *      php/PHPMailer/src/SMTP.php
 *      php/PHPMailer/src/Exception.php
 *
 * 3. For Gmail:
 *    - Go to your Google Account > Security > 2-Step Verification (enable it)
 *    - Then go to Security > App Passwords
 *    - Generate an App Password for "Mail" and set NUTRIGUIDE_SMTP_PASS in the server environment
 *
 * 4. Set NUTRIGUIDE_SMTP_USER, NUTRIGUIDE_SMTP_PASS, and NUTRIGUIDE_SMTP_FROM in the server environment.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/config.php';

function sendVerificationEmail($toEmail, $toName, $code) {
    if (
        SMTP_USER === '' || getSmtpPassword() === '' || SMTP_FROM === '' ||
        SMTP_USER === 'your-email@gmail.com' ||
        SMTP_PASS === 'your-app-password-here' ||
        SMTP_FROM === 'your-email@gmail.com'
    ) {
        error_log('Verification email skipped: configure NUTRIGUIDE_SMTP_USER, NUTRIGUIDE_SMTP_PASS, and NUTRIGUIDE_SMTP_FROM in the server environment.');
        return false;
    }

    if (strtolower(SMTP_HOST) === 'smtp.gmail.com' && strlen(getSmtpPassword()) !== 16) {
        error_log('Verification email skipped: Gmail App Password must be 16 characters after spaces are removed.');
        return false;
    }

    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $body = "
            <div style='font-family:Segoe UI,sans-serif;max-width:480px;margin:auto;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;'>
                <div style='background:#78bc27;padding:24px;text-align:center;'>
                    <h2 style='color:white;margin:0;letter-spacing:1px;'>NutriPh Guide</h2>
                    <p style='color:#eaffcc;margin:4px 0 0;font-size:0.9rem;'>Email Verification</p>
                </div>
                <div style='padding:32px;'>
                    <p style='color:#333;'>Hello <b>$safeName</b>,</p>
                    <p style='color:#555;'>Enter the code below to verify your email address and activate your account:</p>
                    <div style='text-align:center;margin:28px 0;'>
                        <span style='font-size:2.4rem;font-weight:700;letter-spacing:14px;color:#78bc27;background:#f4ffe8;padding:16px 24px;border-radius:8px;border:2px dashed #c1df86;'>$safeCode</span>
                    </div>
                    <p style='color:#888;font-size:0.85rem;'>This code expires in <b>15 minutes</b>. If you did not create an account, you can safely ignore this email.</p>
                </div>
                <div style='background:#f9f9f9;padding:14px;text-align:center;font-size:0.8rem;color:#aaa;'>
                    &copy; NutriPh Guide &mdash; San Antonio Central School
                </div>
            </div>
        ";

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $mail = new PHPMailer(true);
        try {
            configureSmtpMailer($mail);
            $mail->setFrom(SMTP_FROM, SMTP_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = 'NutriPh Guide - Email Verification Code';
            $mail->Body = $body;
            $mail->AltBody = "Hello $toName, your NutriPh Guide verification code is $code. It expires in 15 minutes.";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Verification email attempt $attempt failed for $toEmail: " . $mail->ErrorInfo);
        }
    }

    return false;
}

function configureSmtpMailer(PHPMailer $mail) {
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = getSmtpPassword();
    $mail->SMTPSecure = strtolower(SMTP_ENCRYPTION) === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->Timeout = SMTP_TIMEOUT;
    $mail->CharSet = 'UTF-8';
}

function getSmtpPassword() {
    $password = trim(SMTP_PASS);
    if (strtolower(SMTP_HOST) === 'smtp.gmail.com') {
        $password = preg_replace('/\s+/', '', $password);
    }

    return $password;
}

function sendMalnourishmentEmail($guardianEmail, $guardianName, $studentName, $bmi, $height, $h_unit, $weight, $w_unit, $classification = 'Underweight') {
    // Determine alert styling based on classification
    $alertLabels = [
        'Severe Underweight' => ['label' => 'Severe thinness (WHO BMI-for-age)', 'color' => '#8e2424', 'emoji' => '🚨'],
        'Underweight' => ['label' => 'Malnourished (Underweight)', 'color' => '#c0392b', 'emoji' => '⚠️'],
        'Overweight'  => ['label' => 'At Risk (Overweight)',       'color' => '#e67e22', 'emoji' => '⚠️'],
        'Obese'       => ['label' => 'High Risk (Obese)',          'color' => '#8e44ad', 'emoji' => '🚨'],
    ];
    $alert = $alertLabels[$classification] ?? $alertLabels['Underweight'];
    $alertLabel = $alert['label'];
    $alertColor = $alert['color'];
    $alertEmoji = $alert['emoji'];

    // Calculate weight in kg for personalized protein targets
    $weight_kg    = ($w_unit == 'lbs') ? round($weight * 0.453592, 1) : round((float)$weight, 1);
    $protein_min  = round($weight_kg * 1.0, 1);
    $protein_max  = round($weight_kg * 1.5, 1);
    $protein_limit= round($weight_kg * 2.0, 1);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = getSmtpPassword();
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_NAME);
        $mail->addAddress($guardianEmail, $guardianName);

        $mail->isHTML(true);
        $mail->Subject = "$alertEmoji Health Alert – " . $studentName;
        $mail->Body = "
        <div style='font-family:Segoe UI,sans-serif;max-width:600px;margin:auto;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden;'>
            <div style='background:$alertColor;padding:24px;text-align:center;'>
                <h2 style='color:white;margin:0;'>$alertEmoji Health Alert</h2>
                <p style='color:rgba(255,255,255,0.8);margin:6px 0 0;font-size:0.9rem;'>NutriPh Guide – San Antonio Central School</p>
            </div>
            <div style='padding:28px;'>
                <p style='color:#333;font-size:0.95rem;'>Dear <b>$guardianName</b>,</p>
                <p style='color:#555;'>We would like to inform you that your child, <b>$studentName</b>, has been assessed and classified as <b style='color:$alertColor;'>$alertLabel</b> based on their recent health record.</p>

                <div style='background:#fff5f5;border-left:4px solid $alertColor;border-radius:6px;padding:14px 18px;margin:18px 0;'>
                    <p style='margin:0 0 6px;color:#333;font-weight:700;'>Assessment Summary</p>
                    <p style='margin:4px 0;color:#555;font-size:0.9rem;'>👤 Student: <b>$studentName</b></p>
                    <p style='margin:4px 0;color:#555;font-size:0.9rem;'>📏 Height: <b>$height $h_unit</b></p>
                    <p style='margin:4px 0;color:#555;font-size:0.9rem;'>⚖️ Weight: <b>$weight $w_unit</b></p>
                    <p style='margin:4px 0;color:#555;font-size:0.9rem;'>📊 BMI: <b style='color:$alertColor;'>$bmi</b> ($classification)</p>
                </div>

                <p style='color:#333;font-weight:700;margin-bottom:10px;'>🥗 Recommended Foods for Recovery:</p>
                <table style='width:100%;border-collapse:collapse;font-size:0.88rem;'>
                    <tr style='background:#78bc27;color:white;'>
                        <th style='padding:8px 12px;text-align:left;'>Food Group</th>
                        <th style='padding:8px 12px;text-align:left;'>Examples</th>
                        <th style='padding:8px 12px;text-align:left;'>Benefit</th>
                    </tr>
                    <tr style='background:#f9f9f9;'>
                        <td style='padding:8px 12px;'>🍗 Protein</td>
                        <td style='padding:8px 12px;'>Eggs, chicken, fish, mongo</td>
                        <td style='padding:8px 12px;'>Builds and repairs body</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 12px;'>🍚 Carbohydrates</td>
                        <td style='padding:8px 12px;'>Rice, bread, sweet potato, corn</td>
                        <td style='padding:8px 12px;'>Main energy source</td>
                    </tr>
                    <tr style='background:#f9f9f9;'>
                        <td style='padding:8px 12px;'>🥬 Vegetables</td>
                        <td style='padding:8px 12px;'>Malunggay, kangkong, carrots, squash</td>
                        <td style='padding:8px 12px;'>Vitamins & minerals</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 12px;'>🍌 Fruits</td>
                        <td style='padding:8px 12px;'>Banana, papaya, mango</td>
                        <td style='padding:8px 12px;'>Immune support</td>
                    </tr>
                    <tr style='background:#f9f9f9;'>
                        <td style='padding:8px 12px;'>🥛 Dairy</td>
                        <td style='padding:8px 12px;'>Milk, cheese, yogurt</td>
                        <td style='padding:8px 12px;'>Calcium & bone growth</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 12px;'>🫘 Legumes</td>
                        <td style='padding:8px 12px;'>Sardines, canned fish, beans</td>
                        <td style='padding:8px 12px;'>Affordable nutrition</td>
                    </tr>
                </table>

                <p style='color:#333;font-weight:700;margin:18px 0 10px;'>📊 Recommended Daily Nutrient Intake:</p>
                <p style='color:#888;font-size:0.82rem;margin-bottom:8px;'>Based on your child's weight of <b>$weight $w_unit</b>. These are <b>safe daily targets</b> — do not exceed them.</p>
                <table style='width:100%;border-collapse:collapse;font-size:0.87rem;margin-bottom:6px;'>
                    <tr style='background:#2d6a07;color:white;'>
                        <th style='padding:8px 12px;text-align:left;'>Nutrient</th>
                        <th style='padding:8px 12px;text-align:left;'>Daily Target</th>
                        <th style='padding:8px 12px;text-align:left;'>Do NOT Exceed</th>
                    </tr>
                    <tr style='background:#f9f9f9;'>
                        <td style='padding:8px 12px;'>🔥 Calories</td>
                        <td style='padding:8px 12px;'>1,500 – 2,000 kcal</td>
                        <td style='padding:8px 12px;'>2,200 kcal</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 12px;'>🍗 Protein</td>
                        <td style='padding:8px 12px;'><b>{$protein_min}g – {$protein_max}g</b> <span style='color:#888;font-size:0.8rem;'>(1.0–1.5g per kg)</span></td>
                        <td style='padding:8px 12px;color:#c0392b;'><b>{$protein_limit}g</b> <span style='font-size:0.8rem;'>(2g per kg)</span></td>
                    </tr>
                    <tr style='background:#f9f9f9;'>
                        <td style='padding:8px 12px;'>🍚 Carbohydrates</td>
                        <td style='padding:8px 12px;'>200 – 280g <span style='color:#888;font-size:0.8rem;'>(50–60% of calories)</span></td>
                        <td style='padding:8px 12px;'>320g</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 12px;'>🫒 Healthy Fats</td>
                        <td style='padding:8px 12px;'>40 – 65g <span style='color:#888;font-size:0.8rem;'>(25–35% of calories)</span></td>
                        <td style='padding:8px 12px;'>75g</td>
                    </tr>
                    <tr style='background:#f9f9f9;'>
                        <td style='padding:8px 12px;'>🌾 Fiber</td>
                        <td style='padding:8px 12px;'>20 – 25g</td>
                        <td style='padding:8px 12px;'>35g</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 12px;'>🩸 Iron</td>
                        <td style='padding:8px 12px;'>8 – 10 mg</td>
                        <td style='padding:8px 12px;'>40 mg</td>
                    </tr>
                    <tr style='background:#f9f9f9;'>
                        <td style='padding:8px 12px;'>🦴 Calcium</td>
                        <td style='padding:8px 12px;'>700 – 1,000 mg</td>
                        <td style='padding:8px 12px;'>2,500 mg</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 12px;'>☀️ Vitamin A</td>
                        <td style='padding:8px 12px;'>400 – 600 mcg</td>
                        <td style='padding:8px 12px;'>900 mcg</td>
                    </tr>
                    <tr style='background:#f9f9f9;'>
                        <td style='padding:8px 12px;'>🍊 Vitamin C</td>
                        <td style='padding:8px 12px;'>25 – 45 mg</td>
                        <td style='padding:8px 12px;'>650 mg</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 12px;'>⚡ Zinc</td>
                        <td style='padding:8px 12px;'>5 – 8 mg</td>
                        <td style='padding:8px 12px;'>23 mg</td>
                    </tr>
                </table>

                <div style='background:#fff8e1;border-left:4px solid #f39c12;border-radius:6px;padding:14px 18px;margin:16px 0;'>
                    <p style='margin:0 0 8px;color:#7d4e00;font-weight:700;'>⚠️ Important: Too Much Protein Is Dangerous</p>
                    <ul style='color:#7d4e00;font-size:0.85rem;margin:0;padding-left:18px;line-height:1.9;'>
                        <li>Safe protein for your child: <b>{$protein_min}g – {$protein_max}g per day</b></li>
                        <li>Never exceed <b>{$protein_limit}g/day</b> — excess protein strains the kidneys</li>
                        <li>Too much protein can cause: nausea, fatigue, dehydration, and kidney stress</li>
                        <li>Spread protein intake across 3 meals — do not give all at once</li>
                        <li>Use <b>natural food sources</b> (eggs, fish, mongo) — avoid protein supplements</li>
                    </ul>
                </div>

                <div style='background:#f4ffe8;border:1px dashed #78bc27;border-radius:6px;padding:14px 18px;margin:16px 0;'>
                    <p style='margin:0 0 6px;color:#2d6a07;font-weight:700;'>💡 Daily Feeding Tips</p>
                    <ul style='color:#555;font-size:0.88rem;margin:0;padding-left:18px;line-height:1.9;'>
                        <li>Give <b>3 main meals + 2 healthy snacks</b> daily</li>
                        <li>Follow the <b>Go, Grow, Glow</b> food guide at every meal</li>
                        <li>Avoid junk food, sugary drinks, and instant noodles</li>
                        <li>Ensure clean drinking water at all times</li>
                    </ul>
                </div>

                <p style='color:#c0392b;font-size:0.88rem;'>⚕️ <b>We strongly recommend consulting a doctor or nutritionist</b> for a personalized dietary plan for your child.</p>
            </div>
            <div style='background:#f9f9f9;padding:14px;text-align:center;font-size:0.8rem;color:#aaa;'>
                &copy; NutriPh Guide &mdash; San Antonio Central School
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
