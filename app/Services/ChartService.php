<?php

namespace App\Services;

class ChartService
{
    /**
     * Generate chart image and return temporary file path.
     */
    public function generateChartImage(array $chartData, int $questionId): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null; // GD library not available
        }

        $type = $chartData['type'] ?? 'pie';
        $labels = $chartData['labels'] ?? [];
        $values = $chartData['values'] ?? [];

        if (empty($labels) || empty($values)) {
            return null;
        }

        $width = 600;
        $height = 400;
        $image = imagecreatetruecolor($width, $height);

        // Colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 200, 200, 200);
        $colors = [
            imagecolorallocate($image, 248, 113, 113), // #F87171
            imagecolorallocate($image, 251, 191, 36), // #FBBF24
            imagecolorallocate($image, 52, 211, 153), // #34D399
            imagecolorallocate($image, 96, 165, 250), // #60A5FA
            imagecolorallocate($image, 167, 139, 250), // #A78BFA
            imagecolorallocate($image, 244, 114, 182), // #F472B6
            imagecolorallocate($image, 249, 115, 22), // #F97316
            imagecolorallocate($image, 45, 212, 191), // #2DD4BF
        ];

        // Fill background
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        if ($type === 'pie') {
            $this->drawPieChart($image, $labels, $values, $colors, $black, $width, $height);
        } else {
            $this->drawBarChart($image, $labels, $values, $colors, $black, $gray, $width, $height);
        }

        // Save to temporary file as PNG
        $tempFile = sys_get_temp_dir() . '/chart_' . $questionId . '_' . time() . '.png';
        imagepng($image, $tempFile);

        return $tempFile;
    }

    private function drawPieChart($image, array $labels, array $values, array $colors, $textColor, int $width, int $height): void
    {
        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = min($width, $height) / 3;
        $startAngle = 0;

        $total = array_sum($values);
        if ($total == 0) return;

        $labelY = 50;
        $legendX = 50;
        $legendSpacing = 25;

        foreach ($values as $index => $value) {
            if ($value == 0) continue;

            $percentage = ($value / $total) * 100;
            $angle = ($value / $total) * 360;

            $color = $colors[$index % count($colors)];

            // Draw pie slice
            imagefilledarc(
                $image,
                $centerX,
                $centerY,
                $radius * 2,
                $radius * 2,
                $startAngle,
                $startAngle + $angle,
                $color,
                IMG_ARC_PIE
            );

            // Draw legend
            $label = strip_tags($labels[$index] ?? 'Label ' . ($index + 1));
            if (strlen($label) > 30) {
                $label = substr($label, 0, 27) . '...';
            }
            $legendText = $label . ' (' . $value . ')';

            // Color box
            imagefilledrectangle($image, $legendX, $labelY - 10, $legendX + 15, $labelY + 5, $color);
            imagerectangle($image, $legendX, $labelY - 10, $legendX + 15, $labelY + 5, $textColor);

            // Text
            imagestring($image, 3, $legendX + 20, $labelY - 8, $legendText, $textColor);
            $labelY += $legendSpacing;

            $startAngle += $angle;
        }
    }

    private function drawBarChart($image, array $labels, array $values, array $colors, $textColor, $gridColor, int $width, int $height): void
    {
        $margin = 60;
        $chartWidth = $width - ($margin * 2);
        $chartHeight = $height - ($margin * 2);
        $barWidth = $chartWidth / max(count($values), 1);
        $maxValue = max($values) ?: 1;

        // Draw grid lines
        $gridLines = 5;
        for ($i = 0; $i <= $gridLines; $i++) {
            $y = $margin + ($chartHeight / $gridLines) * $i;
            imageline($image, $margin, $y, $width - $margin, $y, $gridColor);
            $value = $maxValue - (($maxValue / $gridLines) * $i);
            imagestring($image, 2, 10, $y - 7, (int)$value, $textColor);
        }

        // Draw bars
        foreach ($values as $index => $value) {
            $barHeight = ($value / $maxValue) * $chartHeight;
            $x = $margin + ($barWidth * $index) + ($barWidth * 0.1);
            $barActualWidth = $barWidth * 0.8;
            $y = $margin + $chartHeight - $barHeight;

            $color = $colors[$index % count($colors)];
            imagefilledrectangle($image, $x, $y, $x + $barActualWidth, $margin + $chartHeight, $color);
            imagerectangle($image, $x, $y, $x + $barActualWidth, $margin + $chartHeight, $textColor);

            // Value label on top of bar
            imagestring($image, 3, $x + ($barActualWidth / 2) - 10, $y - 20, (string)$value, $textColor);

            // Label below bar
            $label = strip_tags($labels[$index] ?? 'Label ' . ($index + 1));
            if (strlen($label) > 15) {
                $label = substr($label, 0, 12) . '...';
            }
            $labelX = $x + ($barActualWidth / 2) - (strlen($label) * 3);
            imagestring($image, 2, $labelX, $margin + $chartHeight + 5, $label, $textColor);
        }
    }
}
