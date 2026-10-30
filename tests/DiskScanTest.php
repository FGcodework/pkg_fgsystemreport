<?php
use Fg\Component\Fgsystemreport\Administrator\Helper\FgsystemreportHelper as H;
use Fg\Component\Fgsystemreport\Administrator\Helper\DiskScan\TopHeap;

/** root/a (3 x 100 B), root/b (1 x 1000 B), root/skip (1 x 5000 B) */
$tree = static function (): string {
    $d = tmpDir();
    foreach (['a' => [100, 100, 100], 'b' => [1000], 'skip' => [5000]] as $dir => $sizes) {
        mkdir("$d/$dir");
        foreach ($sizes as $i => $n) {
            file_put_contents("$d/$dir/f$i", str_repeat('x', $n));
        }
    }
    return $d;
};

return [
    'totals and top folders sorted largest first' => function () use ($tree) {
        $d = $tree();
        $r = H::scanDiskUsage($d, [], 5);
        assertSame(6300, $r['total']);
        assertSame(['skip', 'b', 'a'], array_map('basename', array_keys($r['dirs'])));
        assertSame(false, $r['partial']);
    },
    'excluded folder names are not entered' => function () use ($tree) {
        $d = $tree();
        $r = H::scanDiskUsage($d, ['skip'], 5);
        assertSame(1300, $r['total']);
        assertTrue(!isset($r['dirs']["$d/skip"]));
    },
    'topN limits the list' => function () use ($tree) {
        assertCount(1, H::scanDiskUsage($tree(), [], 1)['dirs']);
    },
    'item limit makes the scan partial' => function () use ($tree) {
        $r = H::scanDiskUsage($tree(), [], 5, ['max_items' => 3, 'time' => 0]);
        assertSame(true, $r['partial']);
        assertSame('items', $r['reason']);
    },
    'dangling symlink does not abort the scan' => function () use ($tree) {
        $d = $tree();
        symlink("$d/does-not-exist", "$d/a/broken");
        $r = H::scanDiskUsage($d, [], 5);
        assertSame(6300, $r['total']);
        assertSame(false, $r['partial']);
    },
    'directory symlink loop is not followed' => function () use ($tree) {
        $d = $tree();
        symlink($d, "$d/a/loop");
        $r = H::scanDiskUsage($d, [], 5, ['max_items' => 1000, 'time' => 10]);
        assertSame(false, $r['partial']);
        assertSame(6300, $r['total']);
    },
    'TopHeap keeps the smallest at the root' => function () {
        $h = new TopHeap();
        foreach ([5, 1, 9] as $n) {
            $h->insert([$n, "p$n"]);
        }
        assertSame(1, $h->top()[0]);
    },
];
