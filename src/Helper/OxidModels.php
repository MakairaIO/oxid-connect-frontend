<?php

namespace Makaira\OxidConnect\Helper;

use Makaira\OxidConnect\Exception\ModelNotFoundException;
use OxidEsales\Eshop\Application\Model\Article;

use OxidEsales\Eshop\Application\Model\Category;

use OxidEsales\Eshop\Application\Model\Manufacturer;

use function oxNew;

class OxidModels
{
    /**
     * @throws ModelNotFoundException
     */
    public function getArticle(string $id): Article
    {
        $article = oxNew(Article::class);

        if (!$article->load($id)) {
            throw new ModelNotFoundException($id, Article::class);
        }

        return $article;
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getCategory(string $id): Category
    {
        $category = oxNew(Category::class);

        if (!$category->load($id)) {
            throw new ModelNotFoundException($id, Category::class);
        }

        return $category;
    }

    /**
     * @throws ModelNotFoundException
     */
    public function getManufacturer(string $id): Manufacturer
    {
        $manufacturer = oxNew(Manufacturer::class);

        if (!$manufacturer->load($id)) {
            throw new ModelNotFoundException($id, Manufacturer::class);
        }

        return $manufacturer;
    }
}
