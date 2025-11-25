// Mobile menu toggle
const menuToggle = document.createElement('button');
menuToggle.className = 'menu-toggle';
menuToggle.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
    </svg>
`;
menuToggle.setAttribute('aria-label', 'Toggle menu');
menuToggle.setAttribute('aria-expanded', 'false');

// Add mobile menu toggle for small screens
function addMobileMenuToggle() {
    if (window.innerWidth <= 768) {
        const header = document.querySelector('.header-content');
        const sidebar = document.querySelector('.sidebar');
        
        if (!document.querySelector('.menu-toggle')) {
            header.insertBefore(menuToggle, header.firstChild);
            
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                const isOpen = sidebar.classList.contains('active');
                menuToggle.setAttribute('aria-expanded', isOpen.toString());
            });
        }
    } else {
        const existingToggle = document.querySelector('.menu-toggle');
        if (existingToggle) {
            existingToggle.remove();
        }
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) {
            sidebar.classList.remove('active');
        }
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (window.innerWidth <= 768 && sidebar && menuToggle) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = menuToggle.contains(event.target);
        
        if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    }
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// Feedback button handling
document.querySelectorAll('.feedback-btn').forEach(button => {
    button.addEventListener('click', function() {
        const value = this.getAttribute('data-value');
        const feedbackElement = this.closest('.feedback');
        
        // Remove buttons and show thank you message
        const buttons = feedbackElement.querySelector('.feedback-buttons');
        if (buttons) {
            buttons.remove();
        }
        
        const thankYou = document.createElement('p');
        thankYou.textContent = value === 'yes' 
            ? 'Glad to hear that! Thank you for your feedback.' 
            : 'Thank you for your feedback. We\'ll work to improve this page.';
        thankYou.style.color = value === 'yes' ? 'var(--success)' : 'var(--text-secondary)';
        thankYou.style.fontWeight = '500';
        
        feedbackElement.appendChild(thankYou);
    });
});

// Theme toggle functionality
const themeToggle = document.querySelector('.theme-toggle');
if (themeToggle) {
    // Define SVG icons
    const sunIcon = '<circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>';
    const moonIcon = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>';
    
    // Check for saved theme preference or respect OS preference
    const savedTheme = localStorage.getItem('theme');
    const osPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    // Determine the initial theme
    let initialTheme = 'light';
    if (savedTheme === 'dark' || (!savedTheme && osPrefersDark)) {
        initialTheme = 'dark';
    }
    
    // Apply theme on load
    document.documentElement.setAttribute('data-theme', initialTheme);
    
    // Set the correct initial icon
    const svg = themeToggle.querySelector('svg');
    if (svg) {
        svg.innerHTML = initialTheme === 'dark' ? moonIcon : sunIcon;
    }
    
    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        // Update the SVG icon based on theme
        const svg = themeToggle.querySelector('svg');
        if (svg) {
            svg.innerHTML = newTheme === 'dark' ? moonIcon : sunIcon;
        }
    });
}

// Search functionality
const searchInput = document.querySelector('.search-input');
const searchButton = document.querySelector('.search-button');

// Function to perform search
function performSearch(query) {
    if (query) {
        // Redirect to search results page with the query
        window.location.href = `search.html?q=${encodeURIComponent(query)}`;
    }
}

if (searchInput) {
    // Search on Enter key press
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch(this.value.trim());
        }
    });
}

if (searchButton) {
    // Search on button click
    searchButton.addEventListener('click', function() {
        const input = this.closest('.search-container').querySelector('.search-input');
        if (input) {
            performSearch(input.value.trim());
        }
    });
}

// Initialize mobile menu toggle
addMobileMenuToggle();

// Re-check on window resize
window.addEventListener('resize', addMobileMenuToggle);

// Highlight current section in sidebar based on scroll position and current page
function highlightActiveLink() {
    // Get current page filename
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    
    // Remove active class from all links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    
    // Add active class to current page link
    const currentLink = document.querySelector(`.nav-link[href="${currentPage}"]`);
    if (currentLink) {
        currentLink.classList.add('active');
    }
    
    // Highlight based on scroll position
    let sections = document.querySelectorAll('.doc-content h2');
    let navLinks = document.querySelectorAll('.nav-link');
    
    let current = '';
    
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        if (window.scrollY >= sectionTop - 100) {
            current = section.getAttribute('id');
        }
    });
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === `#${current}`) {
            link.classList.add('active');
        }
    });
}

// Call on scroll
window.addEventListener('scroll', highlightActiveLink);

// Call on page load
document.addEventListener('DOMContentLoaded', highlightActiveLink);

// Update footer year dynamically
document.addEventListener('DOMContentLoaded', function() {
    const currentYearElement = document.getElementById('current-year');
    if (currentYearElement) {
        currentYearElement.textContent = new Date().getFullYear();
    }
});