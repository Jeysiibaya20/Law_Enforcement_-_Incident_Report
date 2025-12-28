<?php
$page_title = 'Alertara PH';
$base_url = '';
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
                        <h1 class="hero-title"><img src="assets/css/tara.png" alt="TaraQC Logo" style="height:90px;"><span style="color: white;">ler</span><span style="color: white;">TaraQC</span></h1>
                            <p class="hero-subtitle">Law enforcement and Incident Report</p>
                            <p class="hero-description">Easy access to Update and Reports.</p>
                        <div class="hero-actions">
                            <a href="auth/login.php" class="btn btn-primary btn-lg me-3"><i class="bi bi-box-arrow-in-right"></i> Sign In</a>
                            <a href="#modules" class="btn btn-outline-light btn-lg"><i class="bi bi-grid-3x3-gap"></i> Explore Modules</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="hero-visual">
                            <div class="floating-cards">
                                <div class="card-float card-1"><i class="bi bi-journal"></i><span>Blotter System</span></div>
                                <div class="card-float card-2"><i class="bi bi-person-slash"></i><span>Suspect and Witness Management</span></div>
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
           <div class="cta-actions"><a href="auth/login.php" class="btn btn-primary btn-lg me-3"><i class="bi bi-box-arrow-in-right"></i> Sign In Now</a><a href="#modules" class="btn btn-outline-light btn-lg"><i class="bi bi-info-circle"></i> Learn More</a></div></div></div></div>
        </div>
    </section>
</div>

<style>
.landing-page-body { margin:0; padding:0; overflow-x:hidden; }
.landing-page-container { width:100%; min-height:100vh; margin:0; padding:0; }
.landing-container { min-height:100vh; background: rgba(139,111,71,.1); color: white; width:100%; margin:0; padding:0; }
.hero-section { position:relative; min-height:100vh; display:flex; align-items:center; overflow:hidden; width:100%; margin:0; padding:0; }
.hero-background { position:absolute; top:0; left:0; right:0; bottom:0; z-index:1; }
.logo-background { position:absolute; top:0; left:0; right:0; bottom:0; background:url('logo.png') center/cover no-repeat; opacity:.1; z-index:1; }
.hero-overlay { position:absolute; top:0; left:0; right:0; bottom:0; background: linear-gradient(135deg, rgba(7, 235, 235, 0.85) 0%, rgba(131, 240, 180, 0.41) 50%, rgba(31, 138, 93, 0.53) 100%); z-index:2; }
.hero-content { position:relative; z-index:3; width:100%; margin:0; padding:0; }
.hero-title { font-family:'Libre Baskerville', serif; font-size:3.5rem; font-weight:700; margin-bottom:1rem; line-height:1.2 ; }
.brand-name { display:block; background:linear-gradient(135deg,#FFFFFF 0%, #07e99eff 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
.system-name { display:block; font-size:2rem; color:rgba(255,255,255,.9); margin-top:.5rem; }
.hero-subtitle { font-size:1.3rem; font-weight:500; color:rgba(0, 0, 0, 1); margin-bottom:6rem; }
.hero-description { font-size:1.1rem; color:rgba(0, 0, 0, 1); margin-bottom:2rem; line-height:1.6; }
.hero-actions { display:flex; gap:1rem; flex-wrap:wrap; }
.hero-visual { position:relative; height:500px; display:flex; align-items:center; justify-content:center; }
.floating-cards { position:relative; width:100%; height:100%; }
.card-float { position:absolute; background:rgba(255,255,255,.95); border-radius: var(--border-radius-lg); padding:1.5rem; box-shadow: var(--shadow-xl); backdrop-filter: blur(10px); border:1px solid rgba(139,111,71,.2); display:flex; flex-direction:column; align-items:center; text-align:center; animation: floatCard 6s ease-in-out infinite; }
.card-float i { font-size:2rem; color: var(--main-color); margin-bottom:.5rem; }
.card-float span { font-weight:600; color: var(--text-primary); font-size:.9rem; }
.card-1 { top:10%; left:10%; animation-delay:0s; }
.card-2 { top:20%; right:15%; animation-delay:1.5s; }
.card-3 { bottom:30%; left:10%; animation-delay:3s; }
.card-4 { bottom:10%; right:10%; animation-delay:4.5s; }
.overview-section,.modules-section,.features-section { padding:5rem 0; width:100%; margin:0; }
.cta-section { padding:4rem 0; background: var(--gradient-primary); color: var(--text-white); width:100%; margin:0; }
.section-header { margin-bottom:3rem; }
.section-title { font-family:'Libre Baskerville', serif; font-size:2.5rem; font-weight:700; margin-bottom:1rem; color: var(--text-primary); }
.cta-section .section-title { color: var(--text-white); }
.section-subtitle { font-size:1.1rem; color: var(--text-primary); max-width:600px; margin:0 auto; font-weight:500; }
.cta-section .section-subtitle { color: rgba(255,255,255,.9); }
.overview-card { background: var(--text-white); border-radius: var(--border-radius-lg); padding:2rem; text-align:center; box-shadow: var(--shadow-md); border:1px solid rgba(139,111,71,.1); transition: var(--transition); height:100%; }
.overview-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
.card-icon { width:80px; height:80px; background: var(--gradient-primary); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; font-size:2rem; color: var(--text-white); }
.overview-card h4 { font-weight:600; margin-bottom:1rem; color: var(--text-primary); }
.overview-card p { color: var(--text-primary); line-height:1.6; font-weight:500; }
.module-card { background: var(--text-white); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-md); border:1px solid rgba(139,111,71,.1); transition: var(--transition); height:100%; overflow:hidden; }
.module-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
.module-card.disabled { opacity:.7; cursor:not-allowed; background: rgba(255,255,255,.9); }
.module-card.disabled:hover { transform:none; box-shadow: var(--shadow-md); }
.module-card.disabled .module-content p,.module-card.disabled .module-features li { color: var(--text-primary); font-weight:500; }
.module-header { background: linear-gradient(135deg, #f00000 0%, #253fd4 50%, #d4a574 100%); color:#fff !important; padding:1.5rem; text-align:center; position:relative; }
.module-icon { width:60px; height:60px; background: rgba(255,255,255,.3); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:1.5rem; color:#fff !important; border:2px solid rgba(255,255,255,.5); }
.module-header h4 { font-weight:600; margin:0; color:#fff !important; text-shadow:0 1px 2px rgba(0,0,0,.3); }
.module-content { padding:1.5rem; background: var(--text-white); }
.module-content p { color: var(--text-primary); margin-bottom:1rem; line-height:1.6; font-weight:500; }
.module-features { list-style:none; padding:0; margin:0; }
.module-features li { display:flex; align-items:center; gap:.5rem; margin-bottom:.5rem; font-size:.9rem; color: var(--text-primary); font-weight:500; }
.module-features i { color: var(--success-color); font-size:.8rem; }
.module-footer { padding:1rem 1.5rem; border-top:1px solid rgba(139,111,71,.1); background: rgba(139,111,71,.05); }
.feature-item { text-align:center; padding:1.5rem; }
.feature-icon { width:60px; height:60px; background: var(--gradient-accent); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; font-size:1.5rem; color: var(--text-white); }
.feature-item h5 { font-weight:600; margin-bottom:.5rem; color: var(--text-primary); }
.feature-item p { color: var(--text-primary); font-size:.9rem; line-height:1.5; font-weight:500; }
.cta-content h2 { font-family:'Libre Baskerville', serif; font-size:2.5rem; font-weight:700; margin-bottom:1rem; }
.cta-content p { font-size:1.1rem; margin-bottom:2rem; opacity:.9; }
.cta-actions { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }
.btn-lg { padding:1rem 2rem; font-size:1.1rem; font-weight:600; border-radius: var(--border-radius-sm); transition: var(--transition); }
.btn-primary { background: #4c8a89; border:none; color: var(--text-white); }
.btn-primary:hover { background: var(--gradient-accent); transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.btn-outline-light { border:2px solid rgba(255,255,255,.8); color: var(--text-white); background: transparent; }
.btn-outline-light:hover { background: rgba(255, 255, 255, 0.1); border-color: var(--text-white); color: var(--text-white); }
.btn-outline-primary { border:2px solid var(--main-color); color: var(--main-color); background: transparent; }
.btn-outline-primary:hover { background: var(--main-color); color: var(--text-white); }
.btn-outline-secondary { border:2px solid var(--text-light); color: var(--text-light); background: transparent; cursor:not-allowed; }
@media (max-width:768px){
    .hero-title{font-size:2.5rem}
    .system-name{font-size:1.5rem}
    .hero-subtitle{font-size:1.1rem}
    .hero-description{font-size:1rem}
    .hero-actions{flex-direction:column; align-items:center}
    .hero-visual{height:300px; margin-top:2rem}
    .card-float{padding:1rem}
    .card-float i{font-size:1.5rem}
    .card-float span{font-size:.8rem}
    .section-title{font-size:2rem}
    .cta-content h2{font-size:2rem}
    .overview-section,.modules-section,.features-section,.cta-section{width:100%; margin:0; padding-left:1rem; padding-right:1rem}
}
@keyframes floatCard{0%,100%{transform:translateY(0) rotate(0)}25%{transform:translateY(-10px) rotate(1deg)}50%{transform:translateY(-5px) rotate(-1deg)}75%{transform:translateY(-15px) rotate(.5deg)}}
html{scroll-behavior:smooth}
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

