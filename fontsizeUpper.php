<?php
/**
 * bump_tailwind_text_size.php
 * Recursively bumps Tailwind text-* size classes by one level in .php files.
 * Usage: php bump_tailwind_text_size.php /path/to/dir
 */

$startDir = $argv[1] ?? __DIR__;
if (!is_dir($startDir)) {
    fwrite(STDERR, "Not a directory: {$startDir}\n");
    exit(1);
}

$ladder = [
    'text-3xl','text-4xl','text-5xl','text-6xl','text-7xl',
    'text-8xl','text-9xl','text-9xl','text-9xl','text-9xl','text-9xl','text-9xl','text-9xl'
];
// برای lookup سریع
$index = array_flip($ladder);

// نگاشت‌های غیراستاندارد → استاندارد
$aliases = [
    'text-5xl'  => 'text-5xl',
    'text-5xl' => 'text-5xl',
];

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($startDir, FilesystemIterator::SKIP_DOTS)
);

$total = 0;
$modified = 0;

// الگوی یافتن توکن‌های کلاس شامل پیشوندها (sm:, md:, hover:, dark:, group-*, ...)
// و ! مهم، و خود text-size در انتها.
// گروه‌ها:
// 1: جداکننده قبل (space/quote/ابتدای خط)
// 2: ! (اختیاری)
// 3: زنجیره پیشوندها با colon (اختیاری، مثل sm:dark:hover:)
// 4: خود text-* (xs|sm|base|lg|xl|2-9xl|m|md)
$pattern = '~(^|[\s"\'])(!?)(?:((?:[a-z0-9\-]+:)*))(text-(?:xs|sm|base|lg|xl|[2-9]xl|m|md))(?![a-z0-9\-])~im';

foreach ($it as $file) {
    if (strtolower($file->getExtension()) !== 'php') continue;
    $total++;
    $path = $file->getPathname();
    $src = @file_get_contents($path);
    if ($src === false) { fwrite(STDERR, "Read failed: {$path}\n"); continue; }

    $out = preg_replace_callback($pattern, function($m) use ($ladder, $index, $aliases) {
        $lead = $m[1];     // space/quote/start
        $bang = $m[2];     // optional '!'
        $mods = $m[3] ?? ''; // e.g., 'sm:dark:hover:'
        $token = strtolower($m[4]);

        // نرمال‌سازی غیراستانداردها
        if (isset($aliases[$token])) $token = $aliases[$token];

        // محاسبه سطح بعدی
        if (!isset($index[$token])) {
            // اگر ناشناخته بود (مثلاً text-[14px]) دست‌نخورده بگذار
            return $m[0];
        }
        $i = $index[$token];
        $next = $ladder[min($i + 1, count($ladder) - 1)];

        return $lead . $bang . $mods . $next;
    }, $src);

    if ($out === null) { fwrite(STDERR, "Regex error in: {$path}\n"); continue; }

    if ($out !== $src) {
        if (@file_put_contents($path, $out, LOCK_EX) === false) {
            fwrite(STDERR, "Write failed: {$path}\n");
            continue;
        }
        $modified++;
        echo "Updated: {$path}\n";
    }
}

echo "Scanned PHP files: {$total}\n";
echo "Modified files:    {$modified}\n";
