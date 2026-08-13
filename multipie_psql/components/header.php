<header id="ilearn-header" class="cms-header">
  <div class="header-inner">
    <div class="brand-group">
      <div id="ilearn-brand-logo">
        <span class="brand-name">Multi<span>Pie</span></span>
        <span class="brand-pill">CMS PORTAL</span>
      </div>

      <span class="header-divider"></span>

      <p class="header-subtitle">MultiPie Knowledge &amp; Learning Engine</p>
    </div>

    <form class="header-search" method="get" action="index.php">
      <input type="hidden" name="page" value="<?= e($page) ?>">
      <svg class="icon"><use href="#i-search"/></svg>
      <input
        name="q"
        type="search"
        value="<?= e($_GET['q'] ?? '') ?>"
        placeholder="Search records, learners, financial posts..."
      >
    </form>

    <div class="header-right">
      <a class="btn-templates" href="index.php?page=dashboard">
        <svg class="icon"><use href="#i-book"/></svg>
        <span class="btn-label">HTML Templates</span>
      </a>

      <div class="header-market-badge">
        <span class="dot-live"></span>
        <span class="market-label">NIFTY 50:</span>
        <span class="market-value">24,320.15 (+0.45%)</span>
      </div>

      <a class="icon-btn" href="index.php?page=users" title="Notifications">
        <svg class="icon"><use href="#i-bell"/></svg>
        <span class="notif-dot"></span>
      </a>

      <div class="user-profile">
        <div class="avatar-badge">DB</div>
        <div class="user-profile-text">
          <p>Dinesh Bhatia</p>
          <p>Chief Compliance Admin</p>
        </div>
      </div>
    </div>
  </div>
</header>
