<?php
$appBase    = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')),'/');
if ($appBase === '.') $appBase = '';
$pageTitle  = 'Home';
$activePage = 'home';
$pageScript = 'home';
// Public pages keep the CSP header + Composer autoloader; the dynamic sections
// below are rendered by js/pages/public/home.js via /api/website/* (PublicSite).
require_once __DIR__ . '/public/layout/public_data.php';
?>
<?php include __DIR__ . '/public/layout/header.php'; ?>

<!-- ═══ HERO ══════════════════════════════════════════════════════════════════ -->
<section class="hero">
  <div class="hero-bg-img"></div>
  <div class="hero-particles">
    <span></span><span></span><span></span><span></span><span></span>
    <span></span><span></span><span></span><span></span><span></span>
  </div>
  <div class="container hero-content">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <div class="hero-badge"><i class="bi bi-patch-check-fill"></i>CBC-Aligned Curriculum</div>
        <h1 class="hero-title">
          Where Every Child<br>
          <span class="highlight">Soars to Excellence</span>
        </h1>
        <p class="hero-subtitle">
          Kingsway Preparatory School provides world-class education combining the Kenya
          Competency-Based Curriculum with holistic character development, sports, and
          co-curricular excellence — in the heart of Londiani, Kenya.
        </p>
        <div class="hero-actions">
          <a href="<?= $appBase ?>/admissions.php" class="btn-kw-gold">
            <i class="bi bi-pencil-square"></i>Apply for Admission
          </a>
          <a href="<?= $appBase ?>/about.php" class="btn-kw-outline" style="color:#fff;border-color:rgba(255,255,255,.5);">
            <i class="bi bi-play-circle"></i>Discover Our School
          </a>
        </div>
      </div>
      <div class="col-lg-5 d-none d-lg-block">
        <div class="hero-card">
          <div class="hero-card-title">School at a Glance</div>
          <div id="home-hero-stats"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="scroll-indicator"><span></span>Scroll down</div>
</section>

<!-- ═══ STATS ═════════════════════════════════════════════════════════════════ -->
<section class="section-sm bg-white">
  <div class="container">
    <div class="row g-4" id="home-stats">
      <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card reveal delay-1">
          <div class="stat-icon" style="background:linear-gradient(135deg,#198754,#198754cc);">
            <i class="bi bi-people-fill"></i>
          </div>
          <div class="stat-number" data-target="0" data-suffix="+">0</div>
          <div class="stat-label">Students Enrolled</div>
        </div>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card reveal delay-2">
          <div class="stat-icon" style="background:linear-gradient(135deg,#0d4f2a,#0d4f2acc);">
            <i class="bi bi-person-workspace"></i>
          </div>
          <div class="stat-number" data-target="0" data-suffix="+">0</div>
          <div class="stat-label">Qualified Teachers</div>
        </div>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card reveal delay-3">
          <div class="stat-icon" style="background:linear-gradient(135deg,#f9c80e,#f9c80ecc);">
            <i class="bi bi-trophy-fill"></i>
          </div>
          <div class="stat-number" data-target="0" data-suffix="%">0</div>
          <div class="stat-label">Exam Pass Rate</div>
        </div>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card reveal delay-4">
          <div class="stat-icon" style="background:linear-gradient(135deg,#198754,#198754cc);">
            <i class="bi bi-award-fill"></i>
          </div>
          <div class="stat-number" data-target="0" data-suffix="+">0</div>
          <div class="stat-label">Awards &amp; Honours</div>
        </div>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card reveal delay-5">
          <div class="stat-icon" style="background:linear-gradient(135deg,#0d4f2a,#0d4f2acc);">
            <i class="bi bi-house-door-fill"></i>
          </div>
          <div class="stat-number" data-target="0" data-suffix="">0</div>
          <div class="stat-label">Years of Excellence</div>
        </div>
      </div>
      <div class="col-lg-2 col-md-4 col-6">
        <div class="stat-card reveal delay-6">
          <div class="stat-icon" style="background:linear-gradient(135deg,#f9c80e,#f9c80ecc);">
            <i class="bi bi-heart-fill"></i>
          </div>
          <div class="stat-number" data-target="100" data-suffix="%">0</div>
          <div class="stat-label">Commitment to Learners</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ ABOUT SNIPPET ════════════════════════════════════════════════════════ -->
<section class="section section-alt">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 reveal reveal-left">
        <div class="position-relative">
          <img src="<?= $appBase ?>/images/school-building.jpg"
               onerror="this.src='https://placehold.co/600x420/198754/ffffff?text=Kingsway+School'"
               alt="Kingsway School" class="img-fluid rounded-4 shadow-lg">
          <div class="position-absolute bottom-0 start-0 m-3 bg-white rounded-3 p-3 shadow-sm d-flex align-items-center gap-2">
            <div class="bg-success rounded-2 p-2 text-white"><i class="bi bi-shield-check fs-5"></i></div>
            <div><div class="fw-bold small text-dark">TSC Accredited</div><div class="text-muted" style="font-size:.75rem">Ministry of Education Kenya</div></div>
          </div>
        </div>
      </div>
      <div class="col-lg-6 reveal reveal-right">
        <div class="section-label"><span>About Kingsway</span></div>
        <h2 class="section-title">Building <span>Tomorrow's Leaders</span> Today</h2>
        <p class="section-subtitle mb-4">
          Founded with a vision to provide holistic education, Kingsway Preparatory School
          has grown into one of the leading schools in the Rift Valley region.
          We nurture academic excellence, strong values, and practical life skills.
        </p>
        <div class="row g-3 mb-4">
          <?php
          $pillars = [
            ['icon'=>'bi-book-fill','color'=>'#198754','text'=>'CBC Curriculum','sub'=>'Kenya-aligned learning'],
            ['icon'=>'bi-star-fill','color'=>'#f9c80e','text'=>'Values-Based','sub'=>'Character first approach'],
            ['icon'=>'bi-activity', 'color'=>'#0d6efd','text'=>'Co-Curricular','sub'=>'Sports, arts & clubs'],
            ['icon'=>'bi-house-fill','color'=>'#dc3545','text'=>'Full Boarding','sub'=>'Safe residential school'],
          ];
          foreach ($pillars as $p): ?>
          <div class="col-6">
            <div class="d-flex align-items-start gap-3 p-3 bg-white rounded-3 border h-100">
              <div class="rounded-2 p-2 flex-shrink-0" style="background:<?= $p['color'] ?>22;">
                <i class="bi <?= $p['icon'] ?> fs-5" style="color:<?= $p['color'] ?>"></i>
              </div>
              <div>
                <div class="fw-semibold small"><?= $p['text'] ?></div>
                <div class="text-muted" style="font-size:.78rem"><?= $p['sub'] ?></div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= $appBase ?>/about.php" class="btn-kw-primary">
          <i class="bi bi-arrow-right-circle"></i>Learn More About Us
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ═══ PROGRAMS ══════════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Academic Excellence</span></div>
      <h2 class="section-title">Our <span>Programs & Curriculum</span></h2>
      <p class="section-subtitle mx-auto">Comprehensive CBC-aligned programs from Pre-Primary through Junior Secondary School.</p>
    </div>
    <div class="row g-4" id="home-programs">
    </div>
    <div class="text-center mt-5">
      <a href="<?= $appBase ?>/about.php#programs" class="btn-kw-outline">
        <i class="bi bi-grid-3x3-gap"></i>View All Programs
      </a>
    </div>
  </div>
</section>

<!-- ═══ NEWS + EVENTS ════════════════════════════════════════════════════════ -->
<section class="section section-alt">
  <div class="container">
    <div class="row g-5">

      <!-- Latest News -->
      <div class="col-lg-7">
        <div class="d-flex align-items-center justify-content-between mb-4 reveal">
          <div>
            <div class="section-label"><span>Latest Updates</span></div>
            <h2 class="section-title mb-0">News &amp; <span>Blog</span></h2>
          </div>
          <a href="<?= $appBase ?>/news.php" class="btn-kw-outline" style="white-space:nowrap;padding:8px 18px;font-size:.82rem;">
            All News <i class="bi bi-arrow-right"></i>
          </a>
        </div>
        <div class="row g-4" id="home-news">
        </div>
      </div>

      <!-- Upcoming Events -->
      <div class="col-lg-5">
        <div class="d-flex align-items-center justify-content-between mb-4 reveal">
          <div>
            <div class="section-label"><span>What's Coming</span></div>
            <h2 class="section-title mb-0">Upcoming <span>Events</span></h2>
          </div>
          <a href="<?= $appBase ?>/events.php" class="btn-kw-outline" style="white-space:nowrap;padding:8px 18px;font-size:.82rem;">
            Calendar <i class="bi bi-arrow-right"></i>
          </a>
        </div>
        <div class="bg-white rounded-4 border p-4 reveal">
          <div id="home-events"></div>
          <div class="text-center pt-2">
            <a href="<?= $appBase ?>/events.php" class="read-more justify-content-center">
              View Full Calendar <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ═══ GALLERY ═══════════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-5 reveal reveal-left">
        <div class="section-label"><span>School Life</span></div>
        <h2 class="section-title">Life at <span>Kingsway</span></h2>
        <p class="section-subtitle mb-4">
          From morning assemblies to afternoon sports, from science labs to music
          festivals — every day at Kingsway is filled with purpose, joy, and growth.
        </p>
        <div class="d-flex flex-column gap-3 mb-4">
          <?php foreach ([['bi-house-heart-fill','Boarding & Hostel','Modern dormitories with trained houseparents'],['bi-cup-hot-fill','Catering','Nutritious meals planned by qualified cooks'],['bi-wifi','Digital Learning','Smart classrooms and computer lab']] as $f): ?>
          <div class="d-flex align-items-center gap-3">
            <div class="bg-success bg-opacity-10 rounded-2 p-2 flex-shrink-0">
              <i class="bi <?= $f[0] ?> text-success fs-5"></i>
            </div>
            <div>
              <div class="fw-semibold small"><?= $f[1] ?></div>
              <div class="text-muted" style="font-size:.78rem"><?= $f[2] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-lg-7 reveal reveal-right">
        <div class="gallery-grid" id="home-gallery">
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ UNIFORM STORE ═════════════════════════════════════════════════════════ -->
<section class="section section-alt">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-7 reveal reveal-left">
        <div class="section-label"><span>School Store</span></div>
        <h2 class="section-title">Everything they need to <span>look the part</span></h2>
        <p class="section-subtitle mb-4">Browse official Kingsway uniforms by size, save favourites, and let the school store prepare your child’s order. Uniform purchases are optional and handled separately from school fees.</p>
        <div class="d-flex flex-wrap gap-3">
          <a href="<?= $appBase ?>/uniform_catalog.php" class="btn-kw-primary"><i class="bi bi-bag-heart"></i>Shop Uniforms</a>
          <a href="<?= $appBase ?>/parent_portal.php" class="btn-kw-outline"><i class="bi bi-person"></i>Parent Portal</a>
        </div>
      </div>
      <div class="col-lg-5 reveal reveal-right">
        <div class="p-4 rounded-4 bg-white shadow-sm border-start border-4 border-success">
          <div class="d-flex align-items-center gap-3 mb-3"><div class="rounded-circle bg-success-subtle text-success p-3"><i class="bi bi-phone fs-3"></i></div><div><h5 class="mb-1">Simple parent checkout</h5><p class="text-muted small mb-0">Choose sizes online and pay securely by M-Pesa.</p></div></div>
          <div class="d-flex align-items-center gap-3 mb-3"><div class="rounded-circle bg-warning-subtle text-warning p-3"><i class="bi bi-rulers fs-3"></i></div><div><h5 class="mb-1">Correct fit, less stress</h5><p class="text-muted small mb-0">View available sizes and prices before visiting the store.</p></div></div>
          <div class="d-flex align-items-center gap-3"><div class="rounded-circle bg-primary-subtle text-primary p-3"><i class="bi bi-shield-check fs-3"></i></div><div><h5 class="mb-1">Separate store account</h5><p class="text-muted small mb-0">Uniform purchases never alter school-fee balances.</p></div></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ ADMISSIONS CTA ════════════════════════════════════════════════════════ -->
<section class="cta-banner">
  <div class="container position-relative" style="z-index:1">
    <div class="row align-items-center g-4">
      <div class="col-lg-8 reveal reveal-left">
        <div class="section-label" style="color:var(--gold)"><span>Enrol Today</span></div>
        <h2 class="section-title">Ready to Join the <span style="color:var(--gold)">Kingsway Family?</span></h2>
        <p class="section-subtitle" style="color:rgba(255,255,255,.8);max-width:540px">
          Applications for Term 1 <?= date('Y')+1 ?> are now open. Spaces are available across all grade levels.
          Begin your child's journey to excellence today.
        </p>
      </div>
      <div class="col-lg-4 text-lg-end reveal reveal-right">
        <div class="d-flex flex-column flex-sm-row flex-lg-column gap-3 justify-content-lg-end">
          <a href="<?= $appBase ?>/admissions.php" class="btn-kw-gold">
            <i class="bi bi-pencil-square"></i>Start Application
          </a>
          <a href="<?= $appBase ?>/downloads.php" class="btn-kw-outline" style="color:#fff;border-color:rgba(255,255,255,.4)">
            <i class="bi bi-download"></i>Download Prospectus
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═══ TESTIMONIALS ══════════════════════════════════════════════════════════ -->
<section class="section section-alt">
  <div class="container">
    <div class="text-center mb-5 reveal">
      <div class="section-label justify-content-center"><span>Parent &amp; Alumni Voices</span></div>
      <h2 class="section-title">What Our <span>Community Says</span></h2>
    </div>
    <?php
    $testi = [];
    $db = null;
    try { $db = kw_db(); } catch (\Throwable $e) {}
    if ($db) {
        try { $testi = $db->query("SELECT person_name AS name, role_label AS role, testimonial AS text, video_url, stars FROM school_testimonials WHERE is_active = 1 ORDER BY display_order ASC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) ?: []; } catch (\Throwable $e) { $testi = []; }
    }
    if (empty($testi)) {
        $testi = [
          ['text'=>"Kingsway has transformed my daughter completely. The teachers genuinely care, the CBC teaching is excellent, and she has grown so much in confidence and character.",'name'=>'Mrs. Akinyi Otieno','role'=>'Parent, Grade 6','stars'=>5,'video_url'=>null],
          ['text'=>"As an alumni who went through KCPE here, I can say the foundation Kingsway gave me opened doors to the best secondary schools and beyond. The values still guide me.",'name'=>'Brian Kiprotich','role'=>'Alumni, Class of 2019','stars'=>5,'video_url'=>null],
          ['text'=>"The boarding facilities and pastoral care are exceptional. My son feels at home here. The staff treats every child as their own. We are extremely satisfied.",'name'=>'Mr. Samuel Cheruiyot','role'=>'Parent, Grade 8','stars'=>5,'video_url'=>null],
        ];
    }
    ?>
    <?php
    $carouselId = 'testimonialsCarousel';
    $perSlide = 3;
    $slides = [];
    for ($i = 0; $i < count($testi); $i += $perSlide) {
        $slides[] = array_slice($testi, $i, $perSlide);
    }
    ?>
    <div id="<?= $carouselId ?>" class="carousel slide" data-bs-ride="carousel" data-bs-interval="7000">
      <?php if (count($slides) > 1): ?>
      <div class="carousel-indicators mb-4">
        <?php foreach ($slides as $si => $sl): ?>
        <button type="button" data-bs-target="#<?= $carouselId ?>" data-bs-slide-to="<?= $si ?>" <?= $si === 0 ? 'class="active" aria-current="true"' : '' ?> aria-label="Slide <?= $si+1 ?>"></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="carousel-inner">
        <?php foreach ($slides as $si => $slide): ?>
        <div class="carousel-item<?= $si === 0 ? ' active' : '' ?>">
          <div class="row g-4 justify-content-center">
            <?php foreach ($slide as $t): ?>
            <div class="col-lg-4 col-md-6">
              <div class="testimonial-card h-100">
                <?php if (!empty($t['video_url'])): ?>
                <div class="ratio ratio-16x9 mb-3 rounded-3 overflow-hidden">
                  <?php
                  $vUrl = htmlspecialchars($t['video_url'], ENT_QUOTES, 'UTF-8');
                  if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/)([\w-]+)#', $t['video_url'], $m)) {
                      echo '<iframe src="https://www.youtube.com/embed/' . htmlspecialchars($m[1]) . '" title="Testimonial video" allowfullscreen loading="lazy"></iframe>';
                  } elseif (preg_match('#vimeo\.com/(\d+)#', $t['video_url'], $m)) {
                      echo '<iframe src="https://player.vimeo.com/video/' . htmlspecialchars($m[1]) . '" title="Testimonial video" allowfullscreen loading="lazy"></iframe>';
                  } else {
                      echo '<video controls preload="none" class="w-100 h-100 object-fit-cover"><source src="' . $vUrl . '" type="video/mp4"></video>';
                  }
                  ?>
                </div>
                <?php endif; ?>
                <div class="stars"><?= str_repeat('★', $t['stars'] ?? 5) ?></div>
                <p class="testimonial-text"><?= htmlspecialchars($t['text']) ?></p>
                <div class="testimonial-author">
                  <div class="testimonial-avatar d-flex align-items-center justify-content-center bg-success text-white rounded-circle" style="width:46px;height:46px;font-size:1.1rem;font-weight:700;flex-shrink:0;">
                    <?= strtoupper(substr($t['name'],0,1)) ?>
                  </div>
                  <div>
                    <div class="testimonial-name"><?= htmlspecialchars($t['name']) ?></div>
                    <div class="testimonial-role"><?= htmlspecialchars($t['role']) ?></div>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($slides) > 1): ?>
      <button class="carousel-control-prev" type="button" data-bs-target="#<?= $carouselId ?>" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </button>
      <button class="carousel-control-next" type="button" data-bs-target="#<?= $carouselId ?>" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </button>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ═══ CAREERS TEASER ════════════════════════════════════════════════════════ -->
<section class="section">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-6 reveal reveal-left">
        <div class="section-label"><span>Work With Us</span></div>
        <h2 class="section-title">Build Your Career at <span>Kingsway</span></h2>
        <p class="section-subtitle mb-4">
          We are always looking for passionate educators and support staff who share our
          vision of excellence and child-centred education. Join our team and make a difference.
        </p>
        <a href="<?= $appBase ?>/careers.php" class="btn-kw-primary">
          <i class="bi bi-briefcase-fill"></i>View Open Positions
        </a>
      </div>
      <div class="col-lg-6 reveal reveal-right">
        <div class="row g-3">
          <?php foreach ([['bi-person-check-fill','Competitive Salary','TSC-aligned pay scales and benefits'],['bi-people-fill','Supportive Team','Collaborative, growth-oriented environment'],['bi-patch-check-fill','CPD Programs','Continuous professional development funded'],            ['bi-geo-alt-fill','Londiani, Kenya','Beautiful, serene school grounds']] as $b): ?>
          <div class="col-6">
            <div class="p-3 bg-white border rounded-3 text-center h-100">
              <i class="bi <?= $b[0] ?> text-success fs-3 mb-2 d-block"></i>
              <div class="fw-semibold small mb-1"><?= $b[1] ?></div>
              <div class="text-muted" style="font-size:.78rem"><?= $b[2] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>



<?php include __DIR__ . '/public/layout/footer.php'; ?>
