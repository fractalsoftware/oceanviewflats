<?php

declare(strict_types=1);

namespace OceanViewFlats\Admin\Tests\Integration;

use DOMDocument;
use DOMElement;
use OceanViewFlats\Admin\Http\Request;
use OceanViewFlats\Admin\Tests\Support\AdminHeadAssertionTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Ticket #192 (Parent Spec #191):
 * Light Purple Favicon & Touch Icon Suite for the Admin Console.
 */
final class AdminFaviconSuiteTest extends TestCase
{
    use AdminHeadAssertionTrait;

    protected function setUp(): void
    {
        $this->setUpAdminHeadTestEnvironment();
    }

    public function testSvgFaviconMatchesPublicEmblemGeometryAndUsesLightPurplePalette(): void
    {
        $publicSvgPath = $this->publicDir . '/favicon.svg';
        $adminSvgPath = $this->adminPublicDir . '/favicon.svg';

        $this->assertFileExists($publicSvgPath);
        $this->assertFileExists($adminSvgPath);

        $publicDom = new DOMDocument();
        $this->assertTrue($publicDom->load($publicSvgPath), 'Public favicon.svg must be valid XML');

        $adminDom = new DOMDocument();
        $this->assertTrue($adminDom->load($adminSvgPath), 'Admin favicon.svg must be valid XML');

        $publicSvgEl = $publicDom->documentElement;
        $adminSvgEl = $adminDom->documentElement;
        $this->assertNotNull($publicSvgEl);
        $this->assertNotNull($adminSvgEl);
        $this->assertSame('0 0 96 96', $adminSvgEl->getAttribute('viewBox'));
        $this->assertSame($publicSvgEl->getAttribute('viewBox'), $adminSvgEl->getAttribute('viewBox'));

        $publicPaths = $publicDom->getElementsByTagName('path');
        $adminPaths = $adminDom->getElementsByTagName('path');
        $this->assertSame(1, $publicPaths->length);
        $this->assertSame(1, $adminPaths->length);

        $publicPathNode = $publicPaths->item(0);
        $adminPathNode = $adminPaths->item(0);
        $this->assertNotNull($publicPathNode);
        $this->assertNotNull($adminPathNode);
        $this->assertSame(
            $publicPathNode->getAttribute('d'),
            $adminPathNode->getAttribute('d'),
            'Admin favicon.svg <path d="..."> must preserve exact sun-over-ocean geometry from public/favicon.svg'
        );

        $styles = $adminDom->getElementsByTagName('style');
        $this->assertSame(1, $styles->length);
        $styleNode = $styles->item(0);
        $this->assertNotNull($styleNode);
        $css = strtolower((string) $styleNode->textContent);

        $this->assertMatchesRegularExpression('/path\s*\{\s*fill:\s*#c084fc\s*;?\s*\}/', $css);
        $this->assertMatchesRegularExpression(
            '/@media\s*\(\s*prefers-color-scheme:\s*dark\s*\)\s*\{\s*path\s*\{\s*fill:\s*#d8b4fe\s*;?\s*\}\s*\}/',
            $css
        );
    }

    public function testFavicon96x96PngHasTransparentBackgroundAndLightPurpleEmblem(): void
    {
        $adminPngPath = $this->adminPublicDir . '/favicon-96x96.png';
        $publicPngPath = $this->publicDir . '/favicon-96x96.png';

        $this->assertFileExists($adminPngPath);
        $this->assertFileExists($publicPngPath);

        $adminDecoded = $this->decodeRgbaPng($adminPngPath);
        $publicDecoded = $this->decodeRgbaPng($publicPngPath);

        $this->assertSame(96, $adminDecoded['width']);
        $this->assertSame(96, $adminDecoded['height']);

        // Corners must be fully transparent (alpha === 0)
        foreach ([[0, 0], [95, 0], [0, 95], [95, 95]] as [$x, $y]) {
            $pixel = $this->getPixel($adminDecoded, $x, $y);
            $this->assertSame(0, $pixel[3], "Corner pixel ({$x}, {$y}) in favicon-96x96.png must be transparent");
        }

        $opaqueEmblemPixelCount = 0;
        $alphaMismatches = 0;
        $colorMismatches = 0;
        $adminRgba = $adminDecoded['rgba'];
        $publicRgba = $publicDecoded['rgba'];
        $totalBytes = 96 * 96 * 4;

        for ($i = 0; $i < $totalBytes; $i += 4) {
            $r = ord($adminRgba[$i]);
            $g = ord($adminRgba[$i + 1]);
            $b = ord($adminRgba[$i + 2]);
            $a = ord($adminRgba[$i + 3]);
            $pubA = ord($publicRgba[$i + 3]);

            if ($a !== $pubA) {
                $alphaMismatches++;
            }
            if ($a > 0 && ($r !== 192 || $g !== 132 || $b !== 252)) {
                $colorMismatches++;
            }
            if ($a === 255) {
                $opaqueEmblemPixelCount++;
            }
        }

        $this->assertSame(0, $alphaMismatches, 'Alpha channel must match public/favicon-96x96.png pixel-for-pixel');
        $this->assertSame(0, $colorMismatches, 'All non-transparent pixels in favicon-96x96.png must use #c084fc (192, 132, 252)');
        $this->assertGreaterThan(500, $opaqueEmblemPixelCount, 'Expected substantial #c084fc emblem coverage in favicon-96x96.png');
    }

    public function testFaviconIcoContains48x48TransparentIconWithLightPurpleEmblem(): void
    {
        $adminIcoPath = $this->adminPublicDir . '/favicon.ico';
        $publicIcoPath = $this->publicDir . '/favicon.ico';

        $this->assertFileExists($adminIcoPath);
        $this->assertFileExists($publicIcoPath);

        $adminBytes = (string) file_get_contents($adminIcoPath);
        $publicBytes = (string) file_get_contents($publicIcoPath);

        /** @var array{reserved: int, type: int, count: int} $header */
        $header = unpack('vreserved/vtype/vcount', substr($adminBytes, 0, 6));
        $this->assertSame(0, $header['reserved']);
        $this->assertSame(1, $header['type']);
        $this->assertGreaterThanOrEqual(1, $header['count']);

        /** @var array{w: int, h: int, cc: int, rb: int, planes: int, bc: int, size: int, offset: int} $entry0 */
        $entry0 = unpack('Cw/Ch/Ccc/Crb/vplanes/vbc/Vsize/Voffset', substr($adminBytes, 6, 16));
        $this->assertSame(48, $entry0['w']);
        $this->assertSame(48, $entry0['h']);
        $this->assertSame(32, $entry0['bc']);

        $offset = $entry0['offset'];
        $pixelCount = 48 * 48;

        // First pixel (bottom-left corner in bottom-up DIB) and last pixel (top-right corner) must be transparent
        $firstPixel = array_values((array) unpack('Cb/Cg/Cr/Ca', substr($adminBytes, $offset + 40, 4)));
        $lastPixel = array_values((array) unpack('Cb/Cg/Cr/Ca', substr($adminBytes, $offset + 40 + ($pixelCount - 1) * 4, 4)));
        $this->assertSame(0, $firstPixel[3], 'Corner pixel in 48x48 ICO must be transparent');
        $this->assertSame(0, $lastPixel[3], 'Opposite corner pixel in 48x48 ICO must be transparent');

        $emblemPixels = 0;
        for ($p = 0; $p < $pixelCount; $p++) {
            $pos = $offset + 40 + $p * 4;
            /** @var array{b: int, g: int, r: int, a: int} $adminPx */
            $adminPx = unpack('Cb/Cg/Cr/Ca', substr($adminBytes, $pos, 4));
            /** @var array{b: int, g: int, r: int, a: int} $publicPx */
            $publicPx = unpack('Cb/Cg/Cr/Ca', substr($publicBytes, $pos, 4));

            $this->assertSame($publicPx['a'], $adminPx['a']);
            if ($adminPx['a'] > 0) {
                $this->assertSame(192, $adminPx['r']);
                $this->assertSame(132, $adminPx['g']);
                $this->assertSame(252, $adminPx['b']);
                $emblemPixels++;
            }
        }

        $this->assertGreaterThan(500, $emblemPixels, 'Expected #c084fc emblem pixels in 48x48 favicon.ico');
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int}>
     */
    public static function opaqueWhiteBackgroundIconsProvider(): array
    {
        return [
            'Apple Touch Icon (180x180)' => ['apple-touch-icon.png', 180, 180],
            'Web App Manifest 192x192' => ['web-app-manifest-192x192.png', 192, 192],
            'Web App Manifest 512x512' => ['web-app-manifest-512x512.png', 512, 512],
        ];
    }

    #[DataProvider('opaqueWhiteBackgroundIconsProvider')]
    public function testOpaqueWhiteBackgroundIconsHaveExpectedDimensionsSafeZoneAndLightPurpleEmblem(
        string $filename,
        int $expectedWidth,
        int $expectedHeight
    ): void {
        $adminIconPath = $this->adminPublicDir . '/' . $filename;
        $publicIconPath = $this->publicDir . '/' . $filename;

        $this->assertFileExists($adminIconPath);
        $this->assertFileExists($publicIconPath);

        $adminDecoded = $this->decodeRgbaPng($adminIconPath);
        $publicDecoded = $this->decodeRgbaPng($publicIconPath);

        $this->assertSame($expectedWidth, $adminDecoded['width']);
        $this->assertSame($expectedHeight, $adminDecoded['height']);

        // Corners and safe-zone border pixels must be opaque white (#ffffff -> [255, 255, 255, 255])
        $corners = [
            [0, 0],
            [$expectedWidth - 1, 0],
            [0, $expectedHeight - 1],
            [$expectedWidth - 1, $expectedHeight - 1],
            [(int) floor($expectedWidth * 0.05), (int) floor($expectedHeight * 0.05)],
        ];
        foreach ($corners as [$x, $y]) {
            $this->assertSame(
                [255, 255, 255, 255],
                $this->getPixel($adminDecoded, $x, $y),
                "Safe-zone/corner pixel ({$x}, {$y}) in {$filename} must be opaque #ffffff"
            );
        }

        $solidPurpleCount = 0;
        $nonOpaqueCount = 0;
        $whiteMismatchCount = 0;
        $purpleMismatchCount = 0;
        $adminRgba = $adminDecoded['rgba'];
        $publicRgba = $publicDecoded['rgba'];
        $totalBytes = $expectedWidth * $expectedHeight * 4;

        for ($i = 0; $i < $totalBytes; $i += 4) {
            $r = ord($adminRgba[$i]);
            $g = ord($adminRgba[$i + 1]);
            $b = ord($adminRgba[$i + 2]);
            $a = ord($adminRgba[$i + 3]);

            $pubR = ord($publicRgba[$i]);
            $pubG = ord($publicRgba[$i + 1]);
            $pubB = ord($publicRgba[$i + 2]);

            if ($a !== 255) {
                $nonOpaqueCount++;
            }

            if ($pubR === 255 && $pubG === 255 && $pubB === 255) {
                if ($r !== 255 || $g !== 255 || $b !== 255) {
                    $whiteMismatchCount++;
                }
            }

            if ($pubR === 0 && $pubG === 0 && $pubB === 0) {
                if ($r !== 192 || $g !== 132 || $b !== 252) {
                    $purpleMismatchCount++;
                } else {
                    $solidPurpleCount++;
                }
            }
        }

        $this->assertSame(0, $nonOpaqueCount, "All pixels in {$filename} must be fully opaque");
        $this->assertSame(0, $whiteMismatchCount, "All #ffffff background pixels in {$filename} must remain #ffffff");
        $this->assertSame(0, $purpleMismatchCount, "All core emblem pixels in {$filename} must be #c084fc (192, 132, 252)");
        $this->assertGreaterThan(1000, $solidPurpleCount, "Expected solid #c084fc emblem pixels in {$filename}");
    }

    #[DataProvider('authenticatedAdminRoutesProvider')]
    public function testAuthenticatedRoutesIncludeFaviconAndTouchIconLinksInHead(string $route): void
    {
        $request = new Request('GET', $route);
        $response = $this->app->handle($request, $this->authenticatedAdminSession);

        $this->assertSame(200, $response->getStatusCode(), "Expected HTTP 200 for GET {$route}");
        $this->assertHeadContainsFaviconSuiteLinks($response->getBody(), "Authenticated route {$route}");
    }

    public function testUnauthenticatedLoginRouteIncludesFaviconAndTouchIconLinksInHead(): void
    {
        $emptySession = [];
        $request = new Request('GET', '/login');
        $response = $this->app->handle($request, $emptySession);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertHeadContainsFaviconSuiteLinks($response->getBody(), 'Unauthenticated /login route');
    }

    public function testForbidden403ResponseIncludesFaviconAndTouchIconLinksInHead(): void
    {
        $viewerSession = $this->createViewerSession();

        $request = new Request('GET', '/audit-logs');
        $response = $this->app->handle($request, $viewerSession);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertHeadContainsFaviconSuiteLinks($response->getBody(), 'HTTP 403 Forbidden response');
    }

    public function testStandaloneWebSetupUtilityIncludesFaviconAndTouchIconLinksInHead(): void
    {
        $htmlPortion = $this->extractSetupHtmlShell();
        $this->assertHeadContainsFaviconSuiteLinks($htmlPortion, 'admin/public/setup.php');
    }

    private function assertHeadContainsFaviconSuiteLinks(string $html, string $context): void
    {
        $xpath = $this->createHeadXPath($html);

        // 1. Vector SVG favicon
        $svgLinks = $xpath->query('//head/link[@rel="icon" and @type="image/svg+xml"]');
        $this->assertNotFalse($svgLinks);
        $this->assertSame(1, $svgLinks->length, "{$context} must include <link rel=\"icon\" type=\"image/svg+xml\"> in <head>");
        $svgNode = $svgLinks->item(0);
        $this->assertInstanceOf(DOMElement::class, $svgNode);
        $this->assertStringStartsWith('/favicon.svg', $svgNode->getAttribute('href'));

        // 2. 96x96 PNG favicon
        $pngLinks = $xpath->query('//head/link[@rel="icon" and @type="image/png" and @sizes="96x96"]');
        $this->assertNotFalse($pngLinks);
        $this->assertSame(1, $pngLinks->length, "{$context} must include 96x96 PNG favicon <link> in <head>");
        $pngNode = $pngLinks->item(0);
        $this->assertInstanceOf(DOMElement::class, $pngNode);
        $this->assertStringStartsWith('/favicon-96x96.png', $pngNode->getAttribute('href'));

        // 3. .ico shortcut icon
        $icoLinks = $xpath->query('//head/link[@rel="shortcut icon"]');
        $this->assertNotFalse($icoLinks);
        $this->assertSame(1, $icoLinks->length, "{$context} must include <link rel=\"shortcut icon\"> in <head>");
        $icoNode = $icoLinks->item(0);
        $this->assertInstanceOf(DOMElement::class, $icoNode);
        $this->assertStringStartsWith('/favicon.ico', $icoNode->getAttribute('href'));

        // 4. 180x180 Apple Touch Icon
        $appleLinks = $xpath->query('//head/link[@rel="apple-touch-icon" and @sizes="180x180"]');
        $this->assertNotFalse($appleLinks);
        $this->assertSame(1, $appleLinks->length, "{$context} must include 180x180 Apple Touch Icon <link> in <head>");
        $appleNode = $appleLinks->item(0);
        $this->assertInstanceOf(DOMElement::class, $appleNode);
        $this->assertStringStartsWith('/apple-touch-icon.png', $appleNode->getAttribute('href'));
    }

    /**
     * @param array{width: int, height: int, rgba: string} $decoded
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function getPixel(array $decoded, int $x, int $y): array
    {
        $offset = ($y * $decoded['width'] + $x) * 4;
        return [
            ord($decoded['rgba'][$offset]),
            ord($decoded['rgba'][$offset + 1]),
            ord($decoded['rgba'][$offset + 2]),
            ord($decoded['rgba'][$offset + 3]),
        ];
    }

    /**
     * Decodes an 8-bit RGBA PNG file into its dimensions and flat RGBA byte string.
     *
     * @return array{width: int, height: int, rgba: string}
     */
    private function decodeRgbaPng(string $filePath): array
    {
        $data = (string) file_get_contents($filePath);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($data, 0, 8), "Invalid PNG signature in {$filePath}");

        $pos = 8;
        $len = strlen($data);
        $width = 0;
        $height = 0;
        $idat = '';

        while ($pos < $len) {
            /** @var array{length: int} $lenUnpack */
            $lenUnpack = unpack('Nlength', substr($data, $pos, 4));
            $chunkLen = $lenUnpack['length'];
            $chunkType = substr($data, $pos + 4, 4);
            $chunkData = substr($data, $pos + 8, $chunkLen);
            $pos += 12 + $chunkLen;

            if ($chunkType === 'IHDR') {
                /** @var array{width: int, height: int, depth: int, color: int, comp: int, filter: int, interlace: int} $ihdr */
                $ihdr = unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccomp/Cfilter/Cinterlace', $chunkData);
                $width = $ihdr['width'];
                $height = $ihdr['height'];
                $this->assertSame(8, $ihdr['depth']);
                $this->assertSame(6, $ihdr['color']);
            } elseif ($chunkType === 'IDAT') {
                $idat .= $chunkData;
            } elseif ($chunkType === 'IEND') {
                break;
            }
        }

        $raw = zlib_decode($idat);
        $this->assertIsString($raw, "Failed to decompress IDAT in {$filePath}");

        $bpp = 4;
        $stride = $width * $bpp;
        $prevRow = str_repeat("\x00", $stride);
        $rgbaChunks = [];
        $offset = 0;

        for ($y = 0; $y < $height; $y++) {
            $filterType = ord($raw[$offset]);
            $offset++;

            if ($filterType === 0) {
                $rowStr = substr($raw, $offset, $stride);
            } else {
                $rowStr = str_repeat("\x00", $stride);
                for ($i = 0; $i < $stride; $i++) {
                    $byte = ord($raw[$offset + $i]);
                    $a = $i >= $bpp ? ord($rowStr[$i - $bpp]) : 0;
                    $b = ord($prevRow[$i]);
                    $c = $i >= $bpp ? ord($prevRow[$i - $bpp]) : 0;

                    if ($filterType === 1) {
                        $val = ($byte + $a) & 0xff;
                    } elseif ($filterType === 2) {
                        $val = ($byte + $b) & 0xff;
                    } elseif ($filterType === 3) {
                        $val = ($byte + (($a + $b) >> 1)) & 0xff;
                    } elseif ($filterType === 4) {
                        $p = $a + $b - $c;
                        $pa = abs($p - $a);
                        $pb = abs($p - $b);
                        $pc = abs($p - $c);
                        $pr = ($pa <= $pb && $pa <= $pc) ? $a : (($pb <= $pc) ? $b : $c);
                        $val = ($byte + $pr) & 0xff;
                    } else {
                        $this->fail("Unsupported PNG filter {$filterType} in {$filePath}");
                    }

                    $rowStr[$i] = chr($val);
                }
            }

            $offset += $stride;
            $prevRow = $rowStr;
            $rgbaChunks[] = $rowStr;
        }

        return [
            'width' => $width,
            'height' => $height,
            'rgba' => implode('', $rgbaChunks),
        ];
    }
}
