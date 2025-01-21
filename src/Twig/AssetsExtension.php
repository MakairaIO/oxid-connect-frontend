<?php

namespace Makaira\OxidConnect\Twig;

use OxidEsales\Eshop\Core\Exception\FileException;
use OxidEsales\Eshop\Core\ViewConfig;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function glob;
use function ltrim;
use function reset;
use function rtrim;
use function sprintf;
use function str_replace;

class AssetsExtension extends AbstractExtension
{
    private ?string $modulePath = null;

    public function __construct(private string $moduleId, private ViewConfig $oxidViewConfig)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('makaira_script_path', [$this, 'getScriptPath']),
            new TwigFunction('makaira_style_path', [$this, 'getStylePath']),
        ];
    }

    /**
     * @return string
     * @throws FileException
     */
    public function getScriptPath(): string
    {
        return $this->getAssetUrl('js/*.js');
    }

    /**
     * @return string
     * @throws FileException
     */
    public function getStylePath(): string
    {
        return $this->getAssetUrl('css/*.css');
    }

    /**
     * @throws FileException
     */
    private function getAssetUrl(string $filePattern): string
    {
        return $this->oxidViewConfig->getModuleUrl($this->moduleId, $this->findFirstFile($filePattern));
    }

    /**
     * @throws FileException
     */
    private function findFirstFile(string $filePattern): string
    {
        if (null === $this->modulePath) {
            $modulePath       = $this->oxidViewConfig->getModulePath($this->moduleId);
            $this->modulePath = rtrim($modulePath, '/');
        }

        $files     = glob(sprintf('%s/%s', $this->modulePath, $filePattern));
        $firstFile = reset($files);

        return ltrim(str_replace($this->modulePath, '', $firstFile), '/');
    }
}
