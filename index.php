<?php
// Redirect logged-in admins straight to admin area
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$page_title = 'Alertara PH';
$base_url = '';
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'admin') {
    header('Location: admin/dashboard.php');
    exit();
}
require_once 'includes/landing_header.php';
?>

<div class="landing-container">
    <section class="hero-section">
        <div class="hero-background">
            <div class="logo-background"></div>
            <div class="hero-overlay"></div>
        </div>
        <div class="hero-content">
            <div class="container">
                <div class="row align-items-center min-vh-100">
                    <div class="col-lg-6">
                        <div class="hero-text">
                            <h1 class="hero-title d-flex align-items-center flex-wrap gap-2">
                                <img src="assets/css/tara.png" alt="TaraQC Logo" style="height:70px; object-fit: contain;">
                                <span><span style="color: #1c2541; font-weight: 800;">Aler</span><span style="color: #4c8a89; font-weight: 800;">TaraQC</span></span>
                            </h1>
                            <p class="hero-subtitle">Law enforcement and Incident Report</p>
                            <p class="hero-description">Easy access to Update and Reports.</p>
                            <div class="hero-actions">
                                <a href="./auth/login.php" class="btn btn-primary btn-lg shadow-sm" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; background-color: #2e856e !important; border: 1px solid #2e856e !important; color: #ffffff !important; font-weight: 700;"><i class="bi bi-box-arrow-in-right"></i> Sign In</a>
                                <a href="#modules" class="btn btn-outline-primary btn-lg shadow-sm" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; color: #166534 !important; border: 2px solid #2e856e !important; background-color: #f0fdf4 !important; font-weight: 700 !important;"><i class="bi bi-grid-3x3-gap"></i> Explore Modules</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="hero-visual">
                            <div class="floating-cards">
                                <div class="card-float card-1"><i class="bi bi-journal-text"></i><span>Blotter System</span></div>
                                <div class="card-float card-2"><i class="bi bi-people"></i><span>Suspect & Witness Management</span></div>
                                <div class="card-float card-3"><i class="bi bi-graph-up"></i><span>Analytics & Reports</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="overview-section">
        <div class="container">
            <div class="row"><div class="col-12"><div class="section-header text-center"><h2 class="section-title">System Overview</h2><p class="section-subtitle">---</p></div></div></div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6"><div class="overview-card"><div class="card-icon"><i class="bi bi-shield-check"></i></div><h4>Secure. Compliant. Connected to Protect</h4><p>the essence of modern incident reporting and law enforcement collaboration.</p></div></div>
                <div class="col-lg-4 col-md-6"><div class="overview-card"><div class="card-icon"><i class="bi bi-lightning"></i></div><h4>Real-time Processing</h4><p>Instant updates across all modules with real-time notifications and seamless data synchronization.</p></div></div>
                <div class="col-lg-4 col-md-6"><div class="overview-card"><div class="card-icon"><i class="bi bi-graph-up-arrow"></i></div><h4>Advanced Analytics</h4><p>Comprehensive reporting and analytics to optimize workforce management and operational efficiency.</p></div></div>
            </div>
        </div>
    </section>

    <section id="modules" class="modules-section">
        <div class="container">
            <div class="row"><div class="col-12"><div class="section-header text-center"><h2 class="section-title">Available Modules</h2><p class="section-subtitle">Sign in to access these powerfull Reporting System</p></div></div></div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6"><div class="module-card"><div class="module-header"><div class="module-icon"><i class="bi bi-person-plus"></i></div><h4>#1</h4></div><div class="module-content"><p>EXAMPLE.</p><ul class="module-features"><li><i class="bi bi-check-circle"></i> ...</li><li><i class="bi bi-check-circle"></i> ....</li><li><i class="bi bi-check-circle"></i> Self Service Portal</li><li><i class="bi bi-check-circle"></i> Document Management</li></ul></div><div class="module-footer"><span class="btn btn-outline-secondary disabled"><i class="bi bi-lock"></i> Login Required</span></div></div></div>
        </div>
        </div>
    </section>

    <section class="features-section">
        <div class="container">
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
           <div class="cta-actions"><a href="auth/login.php" class="btn btn-primary btn-lg me-3" style="text-decoration: none; display: inline-block;"><i class="bi bi-box-arrow-in-right"></i> Sign In Now</a><a href="#modules" class="btn btn-outline-light btn-lg" style="text-decoration: none; display: inline-block;"><i class="bi bi-info-circle"></i> Learn More</a></div></div></div></div>
        </div>
    </section>
</div>

<style>
:root {
    --main-color: #4c8a89;
    --gradient-primary: linear-gradient(135deg, #4c8a89 0%, #3a506b 100%);
    --text-primary: #1c2541;
    --text-white: #ffffff;
    --text-light: #cccccc;
    --success-color: #2bae8d;
    --shadow-md: 0 4px 15px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 12px 30px rgba(0, 0, 0, 0.15);
    --shadow-xl: 0 20px 40px rgba(0, 0, 0, 0.2);
    --border-radius-sm: 8px;
    --border-radius-lg: 12px;
    --transition: all 0.3s ease;
    --gradient-accent: linear-gradient(135deg, #2bae8d 0%, #1fa8a0 100%);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    height: 100%;
    width: 100%;
    overflow-x: hidden;
}

.landing-page-body { 
    margin: 0; 
    padding: 0; 
    overflow-x: hidden;
    font-family: 'Quicksand', sans-serif;
}

.landing-page-container { 
    width: 100%; 
    min-height: 100vh; 
    margin: 0; 
    padding: 0;
}

.landing-container { 
    min-height: 100vh; 
    background: rgb(0, 0, 0); 
    color: white; 
    width: 100%; 
    margin: 0; 
    padding: 0;
}

.hero-section { 
    position: relative; 
    min-height: 100vh; 
    display: flex; 
    align-items: center; 
    overflow: hidden; 
    width: 100%; 
    margin: 0; 
    padding: 0;
    margin-top: 56px;
}

.hero-background { 
    position: absolute; 
    top: 0; 
    left: 0; 
    right: 0; 
    bottom: 0; 
    z-index: 1;
}

.logo-background { 
    position: absolute; 
    top: 0; 
    left: 0; 
    right: 0; 
    bottom: 0; 
    background: url('logo.png') center/cover no-repeat; 
    opacity: .1; 
    z-index: 1;
}

.hero-overlay { 
    position: absolute; 
    top: 0; 
    left: 0; 
    right: 0; 
    bottom: 0; 
    background: rgba(255, 255, 255, 0.97); 
    z-index: 2;
}

.hero-content { 
    position: relative; 
    z-index: 3; 
    width: 100%; 
    margin: 0; 
    padding: 2rem 0;
}

.hero-title { 
    font-family: 'Libre Baskerville', serif; 
    font-size: clamp(2rem, 8vw, 3.5rem); 
    font-weight: 700; 
    margin-bottom: 1rem; 
    line-height: 1.2;
}

.brand-name { 
    display: block; 
    background: linear-gradient(135deg, #FFFFFF 0%, rgb(255, 255, 255) 100%); 
    -webkit-background-clip: text; 
    -webkit-text-fill-color: transparent; 
    background-clip: text;
}

.system-name { 
    display: block; 
    font-size: 2rem; 
    color: rgba(0, 0, 0, 0.9); 
    margin-top: .5rem;
}

.hero-subtitle { 
    font-size: clamp(1rem, 3vw, 1.3rem); 
    font-weight: 500; 
    color: rgba(0, 0, 0, 0.76); 
    margin-bottom: 2rem;
}

.hero-description { 
    font-size: clamp(0.9rem, 2vw, 1.1rem); 
    color: rgba(0, 0, 0, 0.69); 
    margin-bottom: 2rem; 
    line-height: 1.6;
}

.hero-actions { 
    display: flex; 
    gap: 1rem; 
    flex-wrap: wrap;
    justify-content: flex-start;
}

.hero-visual { 
    position: relative; 
    height: 500px; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    margin-top: 2rem;
}

.floating-cards { 
    position: relative; 
    width: 100%; 
    height: 100%;
    min-height: 420px;
}

.card-float { 
    position: absolute; 
    background: rgba(255, 255, 255, 0.96); 
    border-radius: var(--border-radius-lg); 
    padding: 1.25rem 1rem; 
    box-shadow: 0 12px 30px rgba(76, 138, 137, 0.18); 
    backdrop-filter: blur(10px); 
    border: 1px solid rgba(76, 138, 137, 0.2); 
    display: flex; 
    flex-direction: column; 
    align-items: center; 
    text-align: center; 
    animation: floatCard 4s ease-in-out infinite;
    width: 165px;
    height: 155px;
    justify-content: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card-float:hover {
    transform: translateY(-8px) scale(1.03);
    box-shadow: 0 18px 36px rgba(76, 138, 137, 0.25);
}

.card-float i { 
    font-size: 2.2rem; 
    color: var(--main-color); 
    margin-bottom: .6rem;
}

.card-float span { 
    font-weight: 700; 
    color: var(--text-primary); 
    font-size: .88rem;
    line-height: 1.3;
    word-break: break-word;
}

.card-1 { 
    top: 10%; 
    left: 8%; 
    animation-delay: 0s;
}

.card-2 { 
    top: 35%; 
    right: 8%; 
    animation-delay: 1.3s;
}

.card-3 { 
    bottom: 10%; 
    left: 22%; 
    animation-delay: 2.6s;
}

.overview-section, .modules-section, .features-section { 
    padding: 3rem 1rem; 
    width: 100%; 
    margin: 0;
}

.cta-section { 
    padding: 3rem 1rem; 
    background: var(--gradient-primary); 
    color: var(--text-white); 
    width: 100%; 
    margin: 0;
}

.section-header { 
    margin-bottom: 2rem;
}

.section-title { 
    font-family: 'Libre Baskerville', serif; 
    font-size: clamp(1.75rem, 5vw, 2.5rem); 
    font-weight: 700; 
    margin-bottom: 1rem; 
    color: var(--text-primary);
}

.cta-section .section-title { 
    color: var(--text-white);
}

.section-subtitle { 
    font-size: clamp(0.9rem, 2vw, 1.1rem); 
    color: var(--text-primary); 
    max-width: 600px; 
    margin: 0 auto; 
    font-weight: 500;
}

.cta-section .section-subtitle { 
    color: rgba(255, 255, 255, .9);
}

.overview-card { 
    background: var(--text-white); 
    border-radius: var(--border-radius-lg); 
    padding: 2rem 1.5rem; 
    text-align: center; 
    box-shadow: var(--shadow-md); 
    border: 1px solid rgba(139, 111, 71, .1); 
    transition: var(--transition); 
    height: 100%;
}

.overview-card:hover { 
    transform: translateY(-5px); 
    box-shadow: var(--shadow-lg);
}

.card-icon { 
    width: 80px; 
    height: 80px; 
    background: var(--gradient-primary); 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    margin: 0 auto 1.5rem; 
    font-size: 2rem; 
    color: var(--text-white);
}

.overview-card h4 { 
    font-weight: 600; 
    margin-bottom: 1rem; 
    color: var(--text-primary);
    font-size: clamp(1rem, 2.5vw, 1.2rem);
}

.overview-card p { 
    color: var(--text-primary); 
    line-height: 1.6; 
    font-weight: 500;
    font-size: clamp(0.85rem, 1.5vw, 0.95rem);
}

.module-card { 
    background: var(--text-white); 
    border-radius: var(--border-radius-lg); 
    box-shadow: var(--shadow-md); 
    border: 1px solid rgba(139, 111, 71, .1); 
    transition: var(--transition); 
    height: 100%; 
    overflow: hidden;
}

.module-card:hover { 
    transform: translateY(-5px); 
    box-shadow: var(--shadow-lg);
}

.module-card.disabled { 
    opacity: .7; 
    cursor: not-allowed; 
    background: rgba(255, 255, 255, .9);
}

.module-card.disabled:hover { 
    transform: none; 
    box-shadow: var(--shadow-md);
}

.module-card.disabled .module-content p, 
.module-card.disabled .module-features li { 
    color: var(--text-primary); 
    font-weight: 500;
}

.module-header { 
    background: linear-gradient(135deg, rgba(76, 138, 137, 0.9) 0%, rgba(58, 80, 107, 0.8) 50%, rgba(28, 37, 65, 0.9) 100%); 
    color: #fff !important; 
    padding: 1.5rem; 
    text-align: center; 
    position: relative;
}

.module-icon { 
    width: 60px; 
    height: 60px; 
    background: rgba(255, 255, 255, .3); 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    margin: 0 auto 1rem; 
    font-size: 1.5rem; 
    color: #fff !important; 
    border: 2px solid rgba(255, 255, 255, .5);
}

.module-header h4 { 
    font-weight: 600; 
    margin: 0; 
    color: #fff !important; 
    text-shadow: 0 1px 2px rgba(0, 0, 0, .3);
}

.module-content { 
    padding: 1.5rem; 
    background: var(--text-white);
}

.module-content p { 
    color: var(--text-primary); 
    margin-bottom: 1rem; 
    line-height: 1.6; 
    font-weight: 500;
}

.module-features { 
    list-style: none; 
    padding: 0; 
    margin: 0;
}

.module-features li { 
    display: flex; 
    align-items: center; 
    gap: .5rem; 
    margin-bottom: .5rem; 
    font-size: .9rem; 
    color: var(--text-primary); 
    font-weight: 500;
}

.module-features i { 
    color: var(--success-color); 
    font-size: .8rem;
}

.module-footer { 
    padding: 1rem 1.5rem; 
    border-top: 1px solid rgba(139, 111, 71, .1); 
    background: rgba(139, 111, 71, .05);
}

.feature-item { 
    text-align: center; 
    padding: 1.5rem;
}

.feature-icon { 
    width: 60px; 
    height: 60px; 
    background: var(--gradient-accent); 
    border-radius: 50%; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    margin: 0 auto 1rem; 
    font-size: 1.5rem; 
    color: var(--text-white);
}

.feature-item h5 { 
    font-weight: 600; 
    margin-bottom: .5rem; 
    color: var(--text-primary);
}

.feature-item p { 
    color: var(--text-primary); 
    font-size: .9rem; 
    line-height: 1.5; 
    font-weight: 500;
}

.cta-content h2 { 
    font-family: 'Libre Baskerville', serif; 
    font-size: clamp(1.75rem, 5vw, 2.5rem); 
    font-weight: 700; 
    margin-bottom: 1rem;
}

.cta-content p { 
    font-size: clamp(0.95rem, 2vw, 1.1rem); 
    margin-bottom: 2rem; 
    opacity: .9;
}

.cta-actions { 
    display: flex; 
    gap: 1rem; 
    justify-content: center; 
    flex-wrap: wrap;
}

.btn-lg { 
    padding: 0.75rem 1.5rem; 
    font-size: clamp(0.9rem, 1.5vw, 1.1rem); 
    font-weight: 600; 
    border-radius: var(--border-radius-sm); 
    transition: var(--transition);
    white-space: nowrap;
}

.btn-primary { 
    background: #4c8a89; 
    border: none; 
    color: var(--text-white);
}

.btn-primary:hover { 
    background: rgba(0, 0, 0, 0.9); 
    transform: translateY(-2px);
    color: var(--text-white);
}

.btn-outline-light { 
    border: 2px solid rgba(255, 255, 255, .8); 
    color: var(--text-white); 
    background: transparent;
}

.btn-outline-light:hover { 
    background: rgba(255, 255, 255, 0.1); 
    border-color: var(--text-white); 
    color: var(--text-white);
}

.btn-outline-primary { 
    border: 2px solid #2e856e !important; 
    color: #166534 !important; 
    background: #f0fdf4 !important;
    font-weight: 700 !important;
}

.btn-outline-primary:hover { 
    background: #2e856e !important; 
    color: #ffffff !important;
}

.btn-outline-secondary { 
    border: 2px solid var(--text-light); 
    color: var(--text-light); 
    background: transparent; 
    cursor: not-allowed;
}

.container {
    width: 100%;
    padding-left: 1rem;
    padding-right: 1rem;
    margin-left: auto;
    margin-right: auto;
}

/* Tablets and smaller desktops */
@media (min-width: 576px) {
    .container { max-width: 540px; }
}

@media (min-width: 768px) {
    .container { max-width: 720px; }
    .hero-actions { justify-content: flex-start; }
    .hero-visual { margin-top: 0; }
}

@media (min-width: 992px) {
    .container { max-width: 960px; }
}

@media (min-width: 1200px) {
    .container { max-width: 1140px; }
}

@media (min-width: 1400px) {
    .container { max-width: 1320px; }
}

/* Mobile-first responsive adjustments */
@media (max-width: 768px) {
    .hero-section {
        min-height: auto;
        padding: 2rem 0;
    }
    
    .hero-content {
        padding: 1rem 0;
    }

    .hero-title {
        font-size: 1.75rem;
    }

    .system-name {
        font-size: 1.2rem;
    }

    .hero-subtitle {
        font-size: 0.95rem;
        margin-bottom: 1.5rem;
    }

    .hero-description {
        font-size: 0.85rem;
        margin-bottom: 1.5rem;
    }

    .hero-actions {
        flex-direction: column;
        gap: 0.75rem;
    }

    .hero-actions .btn {
        width: 100%;
        text-align: center;
    }

    .hero-visual {
        height: auto;
        margin-top: 2rem;
        display: flex;
        justify-content: center;
    }

    .floating-cards {
        position: static;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 1rem;
        width: 100%;
        min-height: auto;
    }

    .card-float {
        position: static;
        width: 140px;
        height: 120px;
        padding: 0.9rem;
        animation: none;
    }

    .card-float i {
        font-size: 1.6rem;
        margin-bottom: 0.35rem;
    }

    .card-float span {
        font-size: 0.78rem;
    }

    .section-title {
        font-size: 1.5rem;
    }

    .overview-section,
    .modules-section,
    .features-section,
    .cta-section {
        padding: 2rem 1rem;
    }

    .overview-card,
    .module-card {
        margin-bottom: 1rem;
    }

    .card-icon {
        width: 60px;
        height: 60px;
        font-size: 1.5rem;
    }

    .overview-card h4 {
        font-size: 1rem;
    }

    .overview-card p {
        font-size: 0.85rem;
    }

    .cta-content h2 {
        font-size: 1.5rem;
    }

    .cta-content p {
        font-size: 0.95rem;
    }

    .min-vh-100 {
        min-height: auto !important;
        padding: 2rem 0;
    }
}

@media (max-width: 576px) {
    .hero-title {
        font-size: 1.5rem;
    }

    .hero-subtitle {
        font-size: 0.85rem;
        margin-bottom: 1rem;
    }

    .hero-description {
        font-size: 0.75rem;
        margin-bottom: 1rem;
    }

    .btn-lg {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
    }

    .hero-visual {
        height: auto;
        margin-top: 1.5rem;
    }

    .card-float {
        width: 130px;
        height: 110px;
        padding: 0.75rem 0.5rem;
    }

    .card-float i {
        font-size: 1.4rem;
        margin-bottom: 0.25rem;
    }

    .card-float span {
        font-size: 0.72rem;
    }

    .section-title {
        font-size: 1.25rem;
    }

    .overview-section,
    .modules-section,
    .features-section,
    .cta-section {
        padding: 1.5rem 0.75rem;
    }

    .cta-actions {
        flex-direction: column;
    }

    .cta-actions .btn {
        width: 100%;
    }

    .min-vh-100 {
        min-height: auto !important;
        padding: 1rem 0;
    }
}

@keyframes floatCard {
    0%, 100% { transform: translateY(0) rotate(0); }
    25% { transform: translateY(-10px) rotate(1deg); }
    50% { transform: translateY(-5px) rotate(-1deg); }
    75% { transform: translateY(-15px) rotate(.5deg); }
}

html {
    scroll-behavior: smooth;
}

.g-4 {
    --bs-gutter-x: 1.5rem;
    --bs-gutter-y: 0;
}

@media (max-width: 768px) {
    .g-4 {
        --bs-gutter-x: 1rem;
    }
}

@media (max-width: 576px) {
    .g-4 {
        --bs-gutter-x: 0.75rem;
    }
}
</style>


<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('a[href^="#"]').forEach(anchor=>{
        anchor.addEventListener('click', function(e){
            e.preventDefault();
            const target=document.querySelector(this.getAttribute('href'));
            if(target){ target.scrollIntoView({ behavior:'smooth', block:'start' }); }
        });
    });
});
</script>

<?php require_once 'includes/landing_footer.php'; ?>
