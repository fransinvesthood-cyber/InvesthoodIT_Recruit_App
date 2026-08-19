<?php
/**
 * ================================================
 * INVESTHOOD IT - Landing Page
 * ================================================
 * Public landing page with flash notification support.
 */

require_once __DIR__ . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Investhood IT - Empowering Tomorrow's Talent Today. Graduate programmes, learnerships, internships, and talent management platform.">
  <title>Investhood IT | Programme & Scarce Skills Platform</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>

  <!-- ===== NAVIGATION ===== -->
  <header class="header" id="header">
    <div class="container header__container">
      <a href="#" class="logo">
        <span class="logo__icon"><i class="fas fa-code"></i></span>
        <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
      </a>
      <nav class="nav" id="nav">
        <ul class="nav__list">
          <li><a href="#home" class="nav__link active">Home</a></li>
          <li><a href="#about" class="nav__link">About Us</a></li>
          <li><a href="#programmes" class="nav__link">Programmes</a></li>
          <li><a href="#opportunities" class="nav__link">Opportunities</a></li>
          <li><a href="#talent" class="nav__link">Talent Community</a></li>
          <li><a href="#skills" class="nav__link">Scarce Skills</a></li>
          <li><a href="#contact" class="nav__link">Contact Us</a></li>
        </ul>
        <div class="nav__actions">
<a href="login.php" class="btn btn--outline btn--sm">Login</a>
          <a href="register.php" class="btn btn--primary btn--sm">Register</a>
        </div>
      </nav>
      <button class="hamburger" id="hamburger" aria-label="Toggle menu">
        <span class="hamburger__line"></span>
        <span class="hamburger__line"></span>
        <span class="hamburger__line"></span>
      </button>
    </div>
  </header>

  <!-- ===== HERO ===== -->
  <section class="hero" id="home">
    <div class="hero__bg"></div>
    <div class="container hero__container">
      <div class="hero__content">
        <span class="hero__badge">Empowering Talent</span>
        <h1 class="hero__title">Empowering Tomorrow's <span class="text-gradient">Talent Today.</span></h1>
        <p class="hero__text">
          Investhood IT connects talented individuals with internships, learnerships, graduate programmes, 
          work-integrated learning opportunities, mentorship initiatives, and future career opportunities.
        </p>
        <div class="hero__actions">
          <a href="register.php" class="btn btn--primary btn--lg">Apply Now <i class="fas fa-arrow-right"></i></a>
          <a href="register.php" class="btn btn--outline btn--lg">Join the Talent Pool</a>
          <a href="login.php" class="btn btn--ghost btn--lg">Explore Opportunities</a>
        </div>
      </div>
      <div class="hero__stats">
        <div class="stat-card" data-count="24">
          <span class="stat-card__number"><span class="counter" data-target="24">0</span>+</span>
          <span class="stat-card__label">Active Programmes</span>
        </div>
        <div class="stat-card" data-count="156">
          <span class="stat-card__number"><span class="counter" data-target="156">0</span>+</span>
          <span class="stat-card__label">Available Opportunities</span>
        </div>
        <div class="stat-card" data-count="3420">
          <span class="stat-card__number"><span class="counter" data-target="3420">0</span>+</span>
          <span class="stat-card__label">Registered Candidates</span>
        </div>
        <div class="stat-card" data-count="1280">
          <span class="stat-card__number"><span class="counter" data-target="1280">0</span>+</span>
          <span class="stat-card__label">Successful Placements</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== ABOUT ===== -->
  <section class="section about" id="about">
    <div class="container">
      <div class="section__header">
        <span class="section__badge">What We Offer</span>
        <h2 class="section__title">About the <span class="text-gradient">Platform</span></h2>
        <p class="section__text">
          A comprehensive talent ecosystem connecting ambitious individuals with transformative career opportunities.
        </p>
      </div>
      <div class="about__grid">
        <div class="feature-card">
          <div class="feature-card__icon"><i class="fas fa-graduation-cap"></i></div>
          <h3 class="feature-card__title">Graduate Programmes</h3>
          <p class="feature-card__text">Structured development programmes for graduates to launch their careers.</p>
        </div>
        <div class="feature-card">
          <div class="feature-card__icon"><i class="fas fa-book-open"></i></div>
          <h3 class="feature-card__title">Learnerships</h3>
          <p class="feature-card__text">Combined theoretical learning with practical workplace experience.</p>
        </div>
        <div class="feature-card">
          <div class="feature-card__icon"><i class="fas fa-briefcase"></i></div>
          <h3 class="feature-card__title">Internships</h3>
          <p class="feature-card__text">Real-world work experience to build professional competence.</p>
        </div>
        <div class="feature-card">
          <div class="feature-card__icon"><i class="fas fa-handshake"></i></div>
          <h3 class="feature-card__title">Work Integrated Learning</h3>
          <p class="feature-card__text">Blend academic theory with practical industry application.</p>
        </div>
        <div class="feature-card">
          <div class="feature-card__icon"><i class="fas fa-users"></i></div>
          <h3 class="feature-card__title">Talent Pool Management</h3>
          <p class="feature-card__text">Build and manage a pipeline of pre-vetted talent.</p>
        </div>
        <div class="feature-card">
          <div class="feature-card__icon"><i class="fas fa-chart-line"></i></div>
          <h3 class="feature-card__title">Skills Development</h3>
          <p class="feature-card__text">Continuous upskilling and professional development programmes.</p>
        </div>
        <div class="feature-card">
          <div class="feature-card__icon"><i class="fas fa-rocket"></i></div>
          <h3 class="feature-card__title">Career Placements</h3>
          <p class="feature-card__text">Strategic placement into roles that match your skills and aspirations.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== PROGRAMMES ===== -->
  <section class="section programmes" id="programmes">
    <div class="container">
      <div class="section__header">
        <span class="section__badge">Our Programmes</span>
        <h2 class="section__title">Featured <span class="text-gradient">Programmes</span></h2>
        <p class="section__text">Discover our range of structured development programmes designed to accelerate your career.</p>
      </div>
      <div class="programmes__grid">
        <div class="programme-card">
          <div class="programme-card__header">
            <span class="programme-card__tag">Graduate</span>
            <h3 class="programme-card__title">Software Engineering Graduate</h3>
          </div>
          <p class="programme-card__desc">A 12-month immersive programme for recent graduates to master modern software engineering practices.</p>
          <div class="programme-card__details">
            <div class="programme-card__detail"><i class="fas fa-clock"></i> 12 Months</div>
            <div class="programme-card__detail"><i class="fas fa-map-marker-alt"></i> Johannesburg</div>
            <div class="programme-card__detail"><i class="fas fa-users"></i> 15 Positions</div>
          </div>
          <div class="programme-card__actions">
            <a href="#" class="btn btn--primary btn--sm">Apply Now</a>
            <a href="#" class="btn btn--ghost btn--sm">View Details</a>
          </div>
        </div>
        <div class="programme-card">
          <div class="programme-card__header">
            <span class="programme-card__tag programme-card__tag--cyan">Learnership</span>
            <h3 class="programme-card__title">IT Systems Development</h3>
          </div>
          <p class="programme-card__desc">NQF Level 5 learnership combining theoretical training with hands-on development experience.</p>
          <div class="programme-card__details">
            <div class="programme-card__detail"><i class="fas fa-clock"></i> 18 Months</div>
            <div class="programme-card__detail"><i class="fas fa-map-marker-alt"></i> Cape Town</div>
            <div class="programme-card__detail"><i class="fas fa-users"></i> 20 Positions</div>
          </div>
          <div class="programme-card__actions">
            <a href="#" class="btn btn--primary btn--sm">Apply Now</a>
            <a href="#" class="btn btn--ghost btn--sm">View Details</a>
          </div>
        </div>
        <div class="programme-card">
          <div class="programme-card__header">
            <span class="programme-card__tag programme-card__tag--amber">Internship</span>
            <h3 class="programme-card__title">Cloud & DevOps Internship</h3>
          </div>
          <p class="programme-card__desc">Hands-on internship working with cloud infrastructure, CI/CD pipelines, and modern DevOps tools.</p>
          <div class="programme-card__details">
            <div class="programme-card__detail"><i class="fas fa-clock"></i> 6 Months</div>
            <div class="programme-card__detail"><i class="fas fa-map-marker-alt"></i> Durban</div>
            <div class="programme-card__detail"><i class="fas fa-users"></i> 10 Positions</div>
          </div>
          <div class="programme-card__actions">
            <a href="#" class="btn btn--primary btn--sm">Apply Now</a>
            <a href="#" class="btn btn--ghost btn--sm">View Details</a>
          </div>
        </div>
        <div class="programme-card">
          <div class="programme-card__header">
            <span class="programme-card__tag programme-card__tag--green">WIL</span>
            <h3 class="programme-card__title">Work Integrated Learning</h3>
          </div>
          <p class="programme-card__desc">Placement programme for students requiring work experience as part of their tertiary qualification.</p>
          <div class="programme-card__details">
            <div class="programme-card__detail"><i class="fas fa-clock"></i> 12 Months</div>
            <div class="programme-card__detail"><i class="fas fa-map-marker-alt"></i> Multiple Locations</div>
            <div class="programme-card__detail"><i class="fas fa-users"></i> 25 Positions</div>
          </div>
          <div class="programme-card__actions">
            <a href="#" class="btn btn--primary btn--sm">Apply Now</a>
            <a href="#" class="btn btn--ghost btn--sm">View Details</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== OPPORTUNITIES ===== -->
  <section class="section opportunities" id="opportunities">
    <div class="container">
      <div class="section__header">
        <span class="section__badge">Open Positions</span>
        <h2 class="section__title">Latest <span class="text-gradient">Opportunities</span></h2>
        <p class="section__text">Browse and filter through available positions matched to your skills and preferences.</p>
      </div>
      <div class="opportunities__filters">
        <div class="filter-group">
          <label for="filter-type" class="filter-label">Programme Type</label>
          <select id="filter-type" class="filter-select">
            <option value="all">All Types</option>
            <option value="graduate">Graduate Programme</option>
            <option value="learnership">Learnership</option>
            <option value="internship">Internship</option>
            <option value="wil">Work Integrated Learning</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="filter-province" class="filter-label">Province</label>
          <select id="filter-province" class="filter-select">
            <option value="all">All Provinces</option>
            <option value="gauteng">Gauteng</option>
            <option value="western-cape">Western Cape</option>
            <option value="kwazulu-natal">KwaZulu-Natal</option>
            <option value="eastern-cape">Eastern Cape</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="filter-qualification" class="filter-label">Qualification</label>
          <select id="filter-qualification" class="filter-select">
            <option value="all">All Levels</option>
            <option value="diploma">Diploma</option>
            <option value="degree">Bachelor's Degree</option>
            <option value="honours">Honours Degree</option>
            <option value="masters">Master's Degree</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="filter-skill" class="filter-label">Skills Category</label>
          <select id="filter-skill" class="filter-select">
            <option value="all">All Skills</option>
            <option value="software">Software Development</option>
            <option value="cloud">Cloud Computing</option>
            <option value="cyber">Cyber Security</option>
            <option value="data">Data Analytics</option>
          </select>
        </div>
      </div>
      <div class="opportunities__grid" id="opportunities-grid">
        <!-- Cards populated by JS -->
      </div>
    </div>
  </section>

  <!-- ===== TALENT COMMUNITY ===== -->
  <section class="section talent" id="talent">
    <div class="container">
      <div class="talent__wrapper">
        <div class="talent__content">
          <span class="section__badge">Join Our Community</span>
          <h2 class="section__title">Talent <span class="text-gradient">Community</span></h2>
          <p class="section__text">Become part of the Investhood talent pool and unlock access to exclusive opportunities.</p>
          <div class="talent__features">
            <div class="talent__feature">
              <div class="talent__feature-icon"><i class="fas fa-upload"></i></div>
              <div class="talent__feature-text">
                <h4>Upload Your CV</h4>
                <p>Showcase your qualifications and experience.</p>
              </div>
            </div>
            <div class="talent__feature">
              <div class="talent__feature-icon"><i class="fas fa-star"></i></div>
              <div class="talent__feature-text">
                <h4>Showcase Your Skills</h4>
                <p>Highlight your technical and professional competencies.</p>
              </div>
            </div>
            <div class="talent__feature">
              <div class="talent__feature-icon"><i class="fas fa-bell"></i></div>
              <div class="talent__feature-text">
                <h4>Personalised Opportunities</h4>
                <p>Receive tailored job and programme recommendations.</p>
              </div>
            </div>
            <div class="talent__feature">
              <div class="talent__feature-icon"><i class="fas fa-sync-alt"></i></div>
              <div class="talent__feature-text">
                <h4>Update Availability Status</h4>
                <p>Let recruiters know when you're ready for new opportunities.</p>
              </div>
            </div>
            <div class="talent__feature">
              <div class="talent__feature-icon"><i class="fas fa-clipboard-check"></i></div>
              <div class="talent__feature-text">
                <h4>Track Your Applications</h4>
                <p>Monitor the status of all your applications in one place.</p>
              </div>
            </div>
            <div class="talent__feature">
              <div class="talent__feature-icon"><i class="fas fa-id-card"></i></div>
              <div class="talent__feature-text">
                <h4>Build Your Profile</h4>
                <p>Create a professional profile that stands out to employers.</p>
              </div>
            </div>
          </div>
          <a href="register.php" class="btn btn--primary btn--lg talent__cta">Join Our Talent Community <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="talent__visual">
          <div class="talent__illustration">
            <i class="fas fa-users"></i>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== SCARCE SKILLS ===== -->
  <section class="section skills" id="skills">
    <div class="container">
      <div class="section__header">
        <span class="section__badge">In-Demand Skills</span>
        <h2 class="section__title">Scarce & <span class="text-gradient">In-Demand Skills</span></h2>
        <p class="section__text">Explore high-demand skills categories with growing opportunities across the technology landscape.</p>
      </div>
      <div class="skills__grid">
        <div class="skill-card">
          <div class="skill-card__icon"><i class="fab fa-react"></i></div>
          <h3 class="skill-card__title">Software Development</h3>
          <span class="skill-card__badge skill-card__badge--high">High Demand</span>
          <p class="skill-card__opportunities"><i class="fas fa-briefcase"></i> 42 Opportunities</p>
          <a href="#" class="btn btn--ghost btn--sm">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="skill-card">
          <div class="skill-card__icon"><i class="fas fa-cloud"></i></div>
          <h3 class="skill-card__title">Cloud Computing</h3>
          <span class="skill-card__badge skill-card__badge--high">High Demand</span>
          <p class="skill-card__opportunities"><i class="fas fa-briefcase"></i> 38 Opportunities</p>
          <a href="#" class="btn btn--ghost btn--sm">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="skill-card">
          <div class="skill-card__icon"><i class="fas fa-shield-alt"></i></div>
          <h3 class="skill-card__title">Cyber Security</h3>
          <span class="skill-card__badge skill-card__badge--high">High Demand</span>
          <p class="skill-card__opportunities"><i class="fas fa-briefcase"></i> 27 Opportunities</p>
          <a href="#" class="btn btn--ghost btn--sm">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="skill-card">
          <div class="skill-card__icon"><i class="fas fa-robot"></i></div>
          <h3 class="skill-card__title">Artificial Intelligence</h3>
          <span class="skill-card__badge skill-card__badge--high">High Demand</span>
          <p class="skill-card__opportunities"><i class="fas fa-briefcase"></i> 31 Opportunities</p>
          <a href="#" class="btn btn--ghost btn--sm">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="skill-card">
          <div class="skill-card__icon"><i class="fas fa-chart-bar"></i></div>
          <h3 class="skill-card__title">Data Analytics</h3>
          <span class="skill-card__badge skill-card__badge--high">High Demand</span>
          <p class="skill-card__opportunities"><i class="fas fa-briefcase"></i> 35 Opportunities</p>
          <a href="#" class="btn btn--ghost btn--sm">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="skill-card">
          <div class="skill-card__icon"><i class="fas fa-network-wired"></i></div>
          <h3 class="skill-card__title">Networking</h3>
          <span class="skill-card__badge skill-card__badge--medium">Medium Demand</span>
          <p class="skill-card__opportunities"><i class="fas fa-briefcase"></i> 18 Opportunities</p>
          <a href="#" class="btn btn--ghost btn--sm">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="skill-card">
          <div class="skill-card__icon"><i class="fab fa-docker"></i></div>
          <h3 class="skill-card__title">DevOps</h3>
          <span class="skill-card__badge skill-card__badge--high">High Demand</span>
          <p class="skill-card__opportunities"><i class="fas fa-briefcase"></i> 24 Opportunities</p>
          <a href="#" class="btn btn--ghost btn--sm">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="skill-card">
          <div class="skill-card__icon"><i class="fas fa-search"></i></div>
          <h3 class="skill-card__title">Business Analysis</h3>
          <span class="skill-card__badge skill-card__badge--medium">Medium Demand</span>
          <p class="skill-card__opportunities"><i class="fas fa-briefcase"></i> 15 Opportunities</p>
          <a href="#" class="btn btn--ghost btn--sm">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== PLATFORM FEATURES ===== -->
  <section class="section features" id="features">
    <div class="container">
      <div class="section__header">
        <span class="section__badge">Platform Capabilities</span>
        <h2 class="section__title">Powerful <span class="text-gradient">Features</span></h2>
        <p class="section__text">Everything you need to manage your career journey from application to placement.</p>
      </div>
      <div class="features__grid">
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-brain"></i></div>
          <h4>Smart Talent Matching</h4>
          <p>AI-powered matching of candidates to opportunities.</p>
        </div>
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-tasks"></i></div>
          <h4>Programme Management</h4>
          <p>End-to-end management of development programmes.</p>
        </div>
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-user-check"></i></div>
          <h4>Placement Management</h4>
          <p>Streamlined placement workflows and tracking.</p>
        </div>
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-calendar-check"></i></div>
          <h4>Attendance Tracking</h4>
          <p>Monitor and manage attendance across programmes.</p>
        </div>
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-certificate"></i></div>
          <h4>Skills Verification</h4>
          <p>Validate and verify candidate competencies.</p>
        </div>
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-lock"></i></div>
          <h4>Secure Document Management</h4>
          <p>Safe storage and sharing of sensitive documents.</p>
        </div>
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-search-plus"></i></div>
          <h4>Application Tracking</h4>
          <p>Real-time tracking of application statuses.</p>
        </div>
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-chart-pie"></i></div>
          <h4>Career Progress Monitoring</h4>
          <p>Track career development milestones and growth.</p>
        </div>
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-mobile-alt"></i></div>
          <h4>Mobile-Friendly Experience</h4>
          <p>Full platform access from any device, anywhere.</p>
        </div>
        <div class="features__card">
          <div class="features__card-icon"><i class="fas fa-user-shield"></i></div>
          <h4>Secure Candidate Profiles</h4>
          <p>Privacy-first profile management with full control.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== TESTIMONIALS ===== -->
  <section class="section testimonials" id="testimonials">
    <div class="container">
      <div class="section__header">
        <span class="section__badge">Success Stories</span>
        <h2 class="section__title">Testimonials & <span class="text-gradient">Achievements</span></h2>
        <p class="section__text">Hear from candidates who have transformed their careers through Investhood IT.</p>
      </div>
      <div class="testimonials__grid">
        <div class="testimonial-card">
          <div class="testimonial-card__rating">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
          </div>
          <p class="testimonial-card__quote">"The graduate programme at Investhood IT gave me the practical skills I needed to launch my career. I went from intern to full-time developer in just 6 months."</p>
          <div class="testimonial-card__author">
            <div class="testimonial-card__avatar">L</div>
            <div>
              <strong>Lebogang M.</strong>
              <span>Software Engineer, TechCorp</span>
            </div>
          </div>
        </div>
        <div class="testimonial-card">
          <div class="testimonial-card__rating">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
          </div>
          <p class="testimonial-card__quote">"Joining the talent pool was the best decision I made. Within two weeks I was matched with a learnership that aligned perfectly with my career goals."</p>
          <div class="testimonial-card__author">
            <div class="testimonial-card__avatar">P</div>
            <div>
              <strong>Priya S.</strong>
              <span>Data Analyst, DataFlow Inc.</span>
            </div>
          </div>
        </div>
        <div class="testimonial-card">
          <div class="testimonial-card__rating">
            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
          </div>
          <p class="testimonial-card__quote">"The mentorship and support I received throughout the programme was exceptional. I now have a clear career path and the skills to achieve my goals."</p>
          <div class="testimonial-card__author">
            <div class="testimonial-card__avatar">T</div>
            <div>
              <strong>Thabo K.</strong>
              <span>Cloud Architect, CloudNet SA</span>
            </div>
          </div>
        </div>
      </div>
      <div class="testimonials__stats">
        <div class="testimonials__stat">
          <span class="testimonials__stat-number">500+</span>
          <span class="testimonials__stat-label">Candidates Placed</span>
        </div>
        <div class="testimonials__stat">
          <span class="testimonials__stat-number">92%</span>
          <span class="testimonials__stat-label">Employment Rate</span>
        </div>
        <div class="testimonials__stat">
          <span class="testimonials__stat-number">50+</span>
          <span class="testimonials__stat-label">Partner Companies</span>
        </div>
        <div class="testimonials__stat">
          <span class="testimonials__stat-number">4.8/5</span>
          <span class="testimonials__stat-label">Candidate Satisfaction</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== CTA ===== -->
  <section class="section cta">
    <div class="container">
      <div class="cta__content">
        <h2 class="cta__title">Your Career Journey <span class="text-gradient">Starts Here.</span></h2>
        <p class="cta__text">Join thousands of professionals who have transformed their careers through Investhood IT. Your future starts today.</p>
        <div class="cta__actions">
<a href="register.php" class="btn btn--primary btn--lg">Register Now <i class="fas fa-user-plus"></i></a>
          <a href="register.php" class="btn btn--outline btn--lg">Apply Today</a>
          <a href="register.php" class="btn btn--ghost btn--lg">Join the Talent Pool</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== FOOTER ===== -->
  <footer class="footer" id="contact">
    <div class="container">
      <div class="footer__grid">
        <div class="footer__col footer__brand">
          <a href="#" class="logo">
            <span class="logo__icon"><i class="fas fa-code"></i></span>
            <span class="logo__text">Investhood <span class="logo__accent">IT</span></span>
          </a>
          <p>Empowering tomorrow's talent today through innovative programmes, skills development, and career opportunities.</p>
          <div class="footer__social">
            <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
          </div>
        </div>
        <div class="footer__col">
          <h4>Quick Links</h4>
          <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="#about">About Us</a></li>
            <li><a href="#programmes">Programmes</a></li>
            <li><a href="#opportunities">Opportunities</a></li>
            <li><a href="#talent">Talent Community</a></li>
          </ul>
        </div>
        <div class="footer__col">
          <h4>Programmes</h4>
          <ul>
            <li><a href="#">Graduate Programmes</a></li>
            <li><a href="#">Learnerships</a></li>
            <li><a href="#">Internships</a></li>
            <li><a href="#">Work Integrated Learning</a></li>
            <li><a href="#">Skills Development</a></li>
          </ul>
        </div>
        <div class="footer__col">
          <h4>Contact Us</h4>
          <ul class="footer__contact">
            <li><i class="fas fa-envelope"></i> info@investhoodit.co.za</li>
            <li><i class="fas fa-phone"></i> +27 (11) 234 5678</li>
            <li><i class="fas fa-map-marker-alt"></i> Johannesburg, South Africa</li>
          </ul>
        </div>
        <div class="footer__col footer__newsletter">
          <h4>Newsletter</h4>
          <p>Stay updated with the latest opportunities and programme news.</p>
          <form class="footer__form">
            <input type="email" placeholder="Your email address" required>
            <button type="submit" class="btn btn--primary btn--sm">Subscribe</button>
          </form>
        </div>
      </div>
      <div class="footer__bottom">
        <div class="footer__legal">
          <a href="#">Privacy Policy</a>
          <a href="#">Terms & Conditions</a>
        </div>
        <p class="footer__copy">&copy; 2025 Investhood IT. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script src="js/script.js"></script>
</body>
</html>
