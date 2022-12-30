/* To inject this as early as possible
 * we use native JS instead of Drupal's behaviors.
*/

// Legacy Check: Transform old localStorage items to newer ones.
function checkLegacy() {
  if (localStorage.getItem('GinDarkMode')) {
    localStorage.setItem('Drupal.gin.darkmode', localStorage.getItem('GinDarkMode'));
    localStorage.removeItem('GinDarkMode');
  }

  if (localStorage.getItem('GinSidebarOpen')) {
    localStorage.setItem('Drupal.gin.toolbarExpanded', localStorage.getItem('GinSidebarOpen'));
    localStorage.removeItem('GinSidebarOpen');
  }
}

checkLegacy();

// Darkmode Check.
function ginInitDarkmode() {
  const darkModeClass = 'gin--dark-mode';
  if (
    localStorage.getItem('Drupal.gin.darkmode') == 1 ||
    (localStorage.getItem('Drupal.gin.darkmode') === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)
  ) {
    document.documentElement.classList.add(darkModeClass);
  } else {
    document.documentElement.classList.contains(darkModeClass) === true && document.documentElement.classList.remove(darkModeClass);
  }
}

ginInitDarkmode();

// GinDarkMode is not set yet.
window.addEventListener('DOMContentLoaded', () => {
  if (!localStorage.getItem('Drupal.gin.darkmode')) {
    localStorage.setItem('Drupal.gin.darkmode', drupalSettings.gin.darkmode);
    ginInitDarkmode();
  }
});

// Sidebar Check.
if (localStorage.getItem('Drupal.gin.toolbarExpanded')) {
  const style = document.createElement('style');
  const className = 'gin-toolbar-inline-styles';
  style.className = className;

  // Sidebar Check.
  if (localStorage.getItem('Drupal.gin.toolbarExpanded') === 'true') {
    style.innerHTML = `
    @media (min-width: 976px) {
      body.gin--vertical-toolbar:not([data-toolbar-menu=open]) {
        padding-inline-start: 240px;
        transition: none;
      }

      .gin--vertical-toolbar .toolbar-menu-administration {
        min-width: var(--ginToolbarWidth, 240px);
        transition: none;
      }
    }
    `;

    const scriptTag = document.querySelector('script');
    scriptTag.parentNode.insertBefore(style, scriptTag);
  } else if (document.getElementsByClassName(className).length > 0) {
    document.getElementsByClassName(className)[0].remove();
  }
}
