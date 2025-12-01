<?php
require_once 'config.php';

// Check if user is logged in and has access to this page
checkPageAccessWithRedirect('about.php');

$current_user = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - <?php echo PROJECT_NAME; ?></title>
    <script src="/assets/js/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/css/roboto.css">
    <link rel="stylesheet" href="/assets/FontAwesome/css/all.css">
    <style>
        body { font-family: 'Roboto', sans-serif; }
    </style>
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900">
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <div class="flex flex-col min-h-screen">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">About Developer</h1>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Project Developer Information</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 p-6">
                <div class="max-w-4xl mx-auto">
                    <!-- Developer Profile Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-8">
                            <div class="flex flex-col md:flex-row items-center md:items-start space-y-4 md:space-y-0 md:space-x-6">
                                <!-- Developer Image -->
                                <div class="flex-shrink-0">
                                    <img src="/uploads/profile/user_1_1762599585.jpg" 
                                         alt="Mehdi Zenhari" 
                                         class="h-32 w-32 rounded-full border-4 border-white shadow-lg object-cover"
                                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'128\' height=\'128\'%3E%3Crect fill=\'%23e5e7eb\' width=\'128\' height=\'128\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%239ca3af\' font-family=\'sans-serif\' font-size=\'48\'%3EMZ%3C/text%3E%3C/svg%3E'">
                                </div>
                                
                                <!-- Developer Info -->
                                <div class="flex-1 text-center md:text-left">
                                    <h2 class="text-3xl font-bold text-white mb-2">Mehdi Zenhari</h2>
                                    <p class="text-blue-100 text-lg mb-4">Web Designer & Developer | Instructor | IT Manager</p>
                                    <div class="flex flex-wrap justify-center md:justify-start gap-4 text-sm text-blue-50">
                                        <div class="flex items-center">
                                            <i class="fas fa-map-marker-alt mr-2"></i>
                                            <span>Shiraz, Fars, Iran</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-envelope text-blue-600 dark:text-blue-400 mr-3 w-5"></i>
                                    <a href="mailto:zenhari@gmail.com" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                                        zenhari@gmail.com
                                    </a>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-phone text-blue-600 dark:text-blue-400 mr-3 w-5"></i>
                                    <a href="tel:+989129382810" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                                        (+98) 912 938 2810
                                    </a>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-globe text-blue-600 dark:text-blue-400 mr-3 w-5"></i>
                                    <a href="https://www.mehdizenhari.com" target="_blank" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                                        www.mehdizenhari.com
                                    </a>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fab fa-linkedin text-blue-600 dark:text-blue-400 mr-3 w-5"></i>
                                    <span class="text-gray-700 dark:text-gray-300">mehdizenhari</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fab fa-telegram text-blue-600 dark:text-blue-400 mr-3 w-5"></i>
                                    <span class="text-gray-700 dark:text-gray-300">+989129382810</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-hashtag text-blue-600 dark:text-blue-400 mr-3 w-5"></i>
                                    <span class="text-gray-700 dark:text-gray-300">@zenhari (Instagram, Twitter, GitHub)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Professional Summary -->
                        <div class="px-6 py-6">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-user-tie text-blue-600 dark:text-blue-400 mr-2"></i>
                                Professional Summary
                            </h3>
                            <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                                Seasoned web designer, developer, and IT instructor with over 23 years of experience in teaching, developing, and managing 50+ websites across diverse industries. Expertise in web technologies, digital marketing, and IT infrastructure management. Proficient in delivering high-quality training and leading technical teams to achieve organizational goals.
                            </p>
                        </div>

                        <!-- Key Skills -->
                        <div class="px-6 py-6 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-tools text-blue-600 dark:text-blue-400 mr-2"></i>
                                Key Skills
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Web Development</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">HTML/CSS, PHP, Joomla, WordPress, Adobe XD, Figma</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Programming</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">VBA (Excel), AutoIT, Node.js, REST API, Telegram Bot Development</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Digital Marketing</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">SEO, Google Analytics, Semrush, Social Media Marketing</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Microsoft Suite</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Excel (Advanced), Access, PowerPoint, SharePoint, Outlook</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Other Tools</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Microsoft Visual Studio, JQuery, Moz, CIW</p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Languages</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Persian (Native), English (Proficient in Reading, Writing, Speaking, Listening)</p>
                                </div>
                            </div>
                        </div>

                        <!-- Professional Experience -->
                        <div class="px-6 py-6 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-briefcase text-blue-600 dark:text-blue-400 mr-2"></i>
                                Professional Experience
                            </h3>
                            <div class="space-y-6">
                                <div class="border-l-4 border-blue-500 pl-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">Senior DevOps Manager</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Saribar Co., Shiraz, Fars</p>
                                        </div>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Apr 2023 – Present</span>
                                    </div>
                                    <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1 mt-2">
                                        <li>Designed scalable cloud infrastructure, reducing costs by 30%</li>
                                        <li>Led cross-functional teams, improving development processes by 40%</li>
                                        <li>Implemented CI/CD automation, accelerating software releases by 50%</li>
                                        <li>Enhanced cybersecurity measures, reducing incidents by 70%</li>
                                    </ul>
                                </div>

                                <div class="border-l-4 border-blue-500 pl-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">IT Manager</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Homa ATC, Tehran</p>
                                        </div>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">May 2020 – Present</span>
                                    </div>
                                    <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1 mt-2">
                                        <li>Configured and maintained enterprise servers and networks</li>
                                        <li>Managed IT projects, optimizing processes and ensuring data security</li>
                                        <li>Developed monitoring and support systems for operational efficiency</li>
                                    </ul>
                                </div>

                                <div class="border-l-4 border-blue-500 pl-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">Technology Manager</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Meraj ATC, Tehran</p>
                                        </div>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Mar 2023 – Present</span>
                                    </div>
                                    <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1 mt-2">
                                        <li>Spearheaded IT projects, implementing innovative tools and systems</li>
                                        <li>Developed data security strategies, enhancing system performance</li>
                                        <li>Coordinated with technical teams for continuous improvements</li>
                                    </ul>
                                </div>

                                <div class="border-l-4 border-blue-500 pl-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">Programming Team Lead</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Raimon Airway, Tehran</p>
                                        </div>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">May 2024 – Present</span>
                                    </div>
                                    <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1 mt-2">
                                        <li>Led a team of 20 developers, ensuring high-quality software delivery</li>
                                        <li>Established coding standards, improving code quality and productivity</li>
                                        <li>Facilitated knowledge transfer with international teams</li>
                                    </ul>
                                </div>

                                <div class="border-l-4 border-blue-500 pl-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">Web Designer & Developer</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Asa Gostar Shiraz, Shiraz, Fars</p>
                                        </div>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Feb 2020 – Present</span>
                                    </div>
                                    <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1 mt-2">
                                        <li>Designed and developed responsive websites for various clients</li>
                                        <li>Optimized websites for SEO and user experience</li>
                                    </ul>
                                </div>

                                <div class="border-l-4 border-blue-500 pl-4">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">Instructor</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">ICDL, Excel, Web Design, Adobe XD, WordPress</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Multiple Institutions (e.g., Industrial Management Org., Pidek, Aryasasol), Shiraz & Bushehr</p>
                                        </div>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">2002 – Present</span>
                                    </div>
                                    <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-1 mt-2">
                                        <li>Delivered training on ICDL, advanced Excel, web design, and digital tools</li>
                                        <li>Developed online courses and animations for remote learning</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Education & Certifications -->
                        <div class="px-6 py-6 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-graduation-cap text-blue-600 dark:text-blue-400 mr-2"></i>
                                Education & Certifications
                            </h3>
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <i class="fas fa-certificate text-blue-600 dark:text-blue-400 mr-3 mt-1"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white">ICDL Certification</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Industrial Management Organization, Jun 2009</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Selected Projects -->
                        <div class="px-6 py-6 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-project-diagram text-blue-600 dark:text-blue-400 mr-2"></i>
                                Selected Projects
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Ticket Sales Website (Parvaz Co.)</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">parvaz-co.ir</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">Developed a platform for purchasing train and flight tickets.</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Educational Website</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">mehdizenhari.com</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">WordPress-based e-learning platform with video content.</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Contest Bot</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">t.me/ramakmatch</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">Designed a Telegram bot for interactive competitions.</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Homa ATC Website</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">homaatc.com</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">Built a comprehensive website and certificate issuance system.</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-2">Saribar Website</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">saribar.com</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">Designed a corporate website with advanced functionalities.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Achievements -->
                        <div class="px-6 py-6 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-trophy text-blue-600 dark:text-blue-400 mr-2"></i>
                                Achievements
                            </h3>
                            <ul class="list-disc list-inside text-sm text-gray-700 dark:text-gray-300 space-y-2">
                                <li>Featured IT Expert on Radio Javan (Fars Radio), May 2015</li>
                                <li>Speaker at Cloud Computing Seminar, Shiraz University of Medical Sciences, Jun 2013</li>
                                <li>Organizer of Aviation Industry Conference, Mar 2022</li>
                                <li>Developed AI-based Workforce Efficiency Tool, 2024</li>
                            </ul>
                        </div>

                        <!-- Research -->
                        <div class="px-6 py-6 border-t border-gray-200 dark:border-gray-600">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">
                                <i class="fas fa-book text-blue-600 dark:text-blue-400 mr-2"></i>
                                Research
                            </h3>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">Iranian Ticket Sales Website Design</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Published: May 2019</p>
                                <p class="text-sm text-gray-700 dark:text-gray-300">Focused on user-friendly platforms for booking travel tickets.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

