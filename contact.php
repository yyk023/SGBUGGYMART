<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db.php';

$sent    = false;
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $email   = trim($_POST['email']   ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($phone) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $toEmail  = 'info@buggyforrent.com';
        $mailSubject = 'SGBUGGYMART Contact Form: ' . ($subject ?: 'General Enquiry');
        $body  = "Name: $name\n";
        $body .= "Email: $email\n";
        $body .= "Phone: $phone\n";
        $body .= "Subject: $subject\n\n";
        $body .= "Message:\n$message\n";

        $headers  = "From: noreply@sgbuggymart.com\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        mail($toEmail, $mailSubject, $body, $headers);
        $success = 'Thank you! Your message has been sent. We will get back to you as soon as possible.';

        // Clear form fields after successful submission
        $_POST = [];
    }
}

include 'header.php';
?>

<style>
    .contact-page {
        background: #ffffff;
        min-height: 70vh;
    }

    /* ── HERO ── */
    .contact-hero {
        background:
            linear-gradient(90deg, rgba(3, 14, 56, 0.88), rgba(3, 14, 56, 0.60), rgba(0,0,0,0.2)),
            url('https://images.unsplash.com/photo-1535131749006-b7f58c99034b?auto=format&fit=crop&w=1800&q=80') center/cover no-repeat;
        padding: 64px 24px 68px;
        color: #ffffff;
        text-align: center;
    }

    .contact-hero h1 {
        margin: 0 0 12px;
        font-size: clamp(28px, 4vw, 48px);
        font-weight: 900;
        letter-spacing: -0.5px;
    }

    .contact-hero p {
        margin: 0 auto;
        max-width: 580px;
        font-size: 16px;
        line-height: 1.7;
        color: rgba(255,255,255,0.88);
    }

    /* ── MAIN LAYOUT ── */
    .contact-body {
        max-width: 1180px;
        margin: 0 auto;
        padding: 60px 24px 80px;
        display: grid;
        grid-template-columns: 1fr 1.2fr;
        gap: 60px;
        align-items: start;
    }

    /* ── LEFT INFO ── */
    .contact-info-kicker {
        font-size: 13px;
        font-weight: 800;
        color: #0066cc;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-bottom: 14px;
    }

    .contact-info h2 {
        margin: 0 0 18px;
        font-size: 34px;
        font-weight: 900;
        color: #111827;
        line-height: 1.15;
        letter-spacing: -0.5px;
    }

    .contact-info-desc {
        color: #6b7280;
        font-size: 15px;
        line-height: 1.75;
        margin-bottom: 38px;
    }

    .contact-detail-list {
        display: grid;
        gap: 22px;
    }

    .contact-detail-item {
        display: flex;
        align-items: flex-start;
        gap: 16px;
    }

    .contact-detail-icon {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: #0066cc;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .contact-detail-text {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .contact-detail-label {
        font-size: 12px;
        font-weight: 800;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .contact-detail-value {
        font-size: 15px;
        color: #111827;
        font-weight: 600;
        line-height: 1.6;
    }

    /* ── RIGHT FORM ── */
    .contact-form-card {
        background: #0f1f3d;
        border-radius: 18px;
        padding: 42px 38px 46px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.15);
    }

    .contact-form-card h3 {
        margin: 0 0 28px;
        font-size: 22px;
        font-weight: 800;
        color: #ffffff;
    }

    .cf-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }

    .cf-group {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .cf-group input,
    .cf-group textarea {
        background: transparent;
        border: none;
        border-bottom: 1px solid rgba(255,255,255,0.25);
        color: #ffffff;
        font-size: 14px;
        padding: 12px 0;
        outline: none;
        transition: border-color 0.2s ease;
        width: 100%;
    }

    .cf-group input::placeholder,
    .cf-group textarea::placeholder {
        color: rgba(255,255,255,0.45);
    }

    .cf-group input:focus,
    .cf-group textarea:focus {
        border-bottom-color: #4da3ff;
    }

    .cf-full {
        margin-bottom: 16px;
    }

    .cf-group textarea {
        resize: vertical;
        min-height: 110px;
        padding-top: 14px;
    }

    .cf-alert {
        padding: 12px 14px;
        border-radius: 8px;
        font-size: 14px;
        margin-bottom: 18px;
        font-weight: 600;
    }

    .cf-alert.success {
        background: rgba(34,197,94,0.15);
        border: 1px solid rgba(34,197,94,0.4);
        color: #86efac;
    }

    .cf-alert.error {
        background: rgba(239,68,68,0.15);
        border: 1px solid rgba(239,68,68,0.4);
        color: #fca5a5;
    }

    .cf-submit {
        width: 100%;
        height: 48px;
        border: none;
        border-radius: 999px;
        background: #0066cc;
        color: #ffffff;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
        margin-top: 24px;
        transition: 0.2s ease;
    }

    .cf-submit:hover {
        background: #0052a3;
        transform: translateY(-2px);
    }

    /* ── MAP ── */
    .contact-map {
        max-width: 1180px;
        margin: 0 auto 70px;
        padding: 0 24px;
        border-radius: 16px;
        overflow: hidden;
    }

    .contact-map iframe {
        width: 100%;
        height: 380px;
        border: 0;
        border-radius: 16px;
        display: block;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
        .contact-body {
            grid-template-columns: 1fr;
            gap: 40px;
            padding: 44px 20px 60px;
        }
    }

    @media (max-width: 560px) {
        .contact-form-card {
            padding: 28px 22px 32px;
        }

        .cf-row {
            grid-template-columns: 1fr;
        }

        .contact-hero {
            padding: 48px 18px 52px;
        }

        .contact-info h2 {
            font-size: 28px;
        }
    }
</style>

<main class="contact-page">

    <section class="contact-hero">
        <h1>Contact Us</h1>
        <p>Have a question about a buggy, need a quote, or just want to say hello? We'd love to hear from you.</p>
    </section>

    <div class="contact-body">

        <!-- LEFT: Company Info -->
        <div class="contact-info">
            <div class="contact-info-kicker">Get in touch</div>
            <h2>How can we help your business?</h2>
            <p class="contact-info-desc">
                We welcome your comments, feedback, and enquiries. Whether you have a question about our buggies, services, or just want to say hello, we'd love to hear from you. Please use the contact form and we'll get back to you as soon as possible.
            </p>

            <div class="contact-detail-list">
                <div class="contact-detail-item">
                    <div class="contact-detail-icon">📍</div>
                    <div class="contact-detail-text">
                        <span class="contact-detail-label">Address</span>
                        <span class="contact-detail-value">2 Pandan Road, 609254 Singapore</span>
                    </div>
                </div>

                <div class="contact-detail-item">
                    <div class="contact-detail-icon">📞</div>
                    <div class="contact-detail-text">
                        <span class="contact-detail-label">Phone</span>
                        <span class="contact-detail-value">
                            Tel: (65) 6264 2155<br>
                            Tel: (65) 6265 9927<br>
                            Tel: (65) 6266 5368
                        </span>
                    </div>
                </div>

                <div class="contact-detail-item">
                    <div class="contact-detail-icon">✉️</div>
                    <div class="contact-detail-text">
                        <span class="contact-detail-label">Email</span>
                        <span class="contact-detail-value">
                            <a href="mailto:info@buggyforrent.com" style="color:#0066cc;">info@buggyforrent.com</a>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Contact Form -->
        <div class="contact-form-card">
            <h3>Send us a message</h3>

            <?php if (!empty($success)): ?>
                <div class="cf-alert success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="cf-alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" action="contact.php">
                <div class="cf-row">
                    <div class="cf-group">
                        <input type="text" name="name" placeholder="Name *" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    <div class="cf-group">
                        <input type="email" name="email" placeholder="Email *" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="cf-row">
                    <div class="cf-group">
                        <input type="text" name="phone" placeholder="Phone Number *" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    <div class="cf-group">
                        <input type="text" name="subject" placeholder="Subject" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                    </div>
                </div>

                <div class="cf-full cf-group">
                    <textarea name="message" placeholder="Describe your message *" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="cf-submit">Submit your message</button>
            </form>
        </div>

    </div>

    <!-- Google Map -->
    <div class="contact-map">
        <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3988.7!2d103.7574!3d1.3138!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31da1a2a2a2a2a2a%3A0x0!2s2+Pandan+Road%2C+Singapore+609254!5e0!3m2!1sen!2ssg!4v1"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
        ></iframe>
    </div>

</main>

<?php include 'footer.php'; ?>
