<?php

namespace SitemapSlicer\SitemapIndexSlicer;

/*
 * LingTalfi 2015-10-10
 */
use Bat\FileSystemTool;
use SitemapBuilderBox\Builder\XmlSitemapBuilder;
use SitemapBuilderBox\Builder\XmlSitemapBuilderPlugin\GoogleImageXmlSitemapBuilderPlugin;
use SitemapBuilderBox\Builder\XmlSitemapBuilderPlugin\GoogleMobileXmlSitemapBuilderPlugin;
use SitemapBuilderBox\Builder\XmlSitemapBuilderPlugin\GoogleVideoXmlSitemapBuilderPlugin;
use SitemapBuilderBox\Objects\Sitemap;
use SitemapBuilderBox\Objects\SitemapIndex;
use SitemapBuilderBox\Objects\SitemapIndexSitemap;
use SitemapSlicer\SitemapSlice\SitemapSliceInterface;

class AuthorSitemapIndexSlicer implements SitemapIndexSlicerInterface
{
    private $filePath;
    /**
     * @var SitemapSliceInterface[]
     */
    private $slices;
    private $_defaultSliceWidth;
    private $_onWarning;
    private $urlCb;


    // internal
    /**
     * @var XmlSitemapBuilder
     */
    private $__builder;
    /**
     * @var SitemapIndex
     */
    private $__sitemapIndex;

    /**
     * @var SitemapIndexSitemap
     */
    private $__sitemapIndexSitemap;

    /**
     * @var Sitemap
     */
    private $__sitemap;
    private $__urlCb;
    private $__curSliceNb;

    /**
     * @var SitemapSliceInterface
     */
    private $__curSlice;
    private $__nbUrlsProcessed;
    private $__sliceWidth;
    private $__tableOffset;


    public function __construct()
    {
        $this->slices = [];
        $this->_defaultSliceWidth = 10000;
    }

    public static function create()
    {
        return new static();
    }

    /**
     * @return SitemapIndexSlicerInterface
     */
    public function file($filePath)
    {
        FileSystemTool::touchDone($filePath); // get rid of perms problem right away
        $this->filePath = $filePath;
        return $this;
    }

    /**
     * string:url       f ( string:filePath )
     */
    public function url(callable $f)
    {
        $this->urlCb = $f;
        return $this;
    }

    /**
     * @return SitemapIndexSlicerInterface
     */
    public function defaultSliceWidth($n)
    {
        $this->_defaultSliceWidth = (int)$n;
        return $this;
    }

    /**
     * @return SitemapIndexSlicerInterface
     */
    public function onWarning(callable $f)
    {
        $this->_onWarning = $f;
        return $this;
    }


    /**
     * @return SitemapIndexSlicerInterface
     */
    public function addSitemapSlice(SitemapSliceInterface $slice)
    {
        $this->slices[] = $slice;
        return $this;
    }

    public function execute()
    {

        $this->__builder = $this->getSiteMapBuilder();
        $this->__sitemapIndex = SitemapIndex::create();
        $this->__sitemapIndexSitemap = SitemapIndexSitemap::create();
        $this->__sitemap = Sitemap::create();
        $this->__urlCb = (is_callable($this->urlCb)) ? $this->urlCb : function ($filePath) {
            return 'http://dummy.com/' . basename($filePath);
        };


        $this->__curSliceNb = 0;
        $this->__nbUrlsProcessed = 0;
        if ($this->slices) {

            foreach ($this->slices as $slice) {
                $this->__curSliceNb++;
                $this->__curSlice = $slice;
                $nSlice = $this->__curSliceNb;

                try {


                    // define sliceWidth
                    if (null === ($this->__sliceWidth = $slice->getSliceWidth())) {
                        $this->__sliceWidth = $this->_defaultSliceWidth;
                    }


                    $bindures = $slice->getTableBindures();
                    foreach ($bindures as $bIndex => $b) {


                        $this->__tableOffset = 0;

                        $nbItems = (int)$b->getCount();
                        $rowsCb = $b->getRowsCallback();
                        $convert = $b->getConvertToSitemapEntryCallback();

                        $this->parseRows($rowsCb, $convert, $nbItems);


                    }
                } catch (\Exception $e) {
                    $this->warning((string)$e);
                }


            }
            $this->newSitemap();
        }
        $this->__builder->createSitemapIndexFile($this->__sitemapIndex, $this->filePath);
    }

    //------------------------------------------------------------------------------/
    // 
    //------------------------------------------------------------------------------/
    private function warning($m)
    {
        if (is_callable($this->_onWarning)) {
            call_user_func($this->_onWarning, $m);
        }
    }


    private function parseRows(callable $rowsCb, callable $convertCb, $nbItems)
    {

        $rows = call_user_func($rowsCb, $this->__tableOffset, $this->__sliceWidth);
        if (is_array($rows)) {
            foreach ($rows as $rIndex => $row) {
                $url = call_user_func($convertCb, $row);
                $this->__sitemap->addUrl($url);

                $this->__tableOffset++;
                $this->__nbUrlsProcessed++;

                if ($this->__nbUrlsProcessed >= $this->__sliceWidth) {
                    $this->__nbUrlsProcessed = 0;
                    $this->newSitemap();

                    if ($nbItems > $this->__tableOffset) {
                        $this->parseRows($rowsCb, $convertCb, $nbItems);
                    }
                    break;
                }
            }

        }
        else {
            $this->warning(sprintf("Invalid rows type, expected array, %s given", gettype($rows)));
        }
    }


    /**
     * @return XmlSitemapBuilder
     */
    private function getSiteMapBuilder()
    {
        $o = new XmlSitemapBuilder();
        $o->registerPlugin(new GoogleVideoXmlSitemapBuilderPlugin());
        $o->registerPlugin(new GoogleImageXmlSitemapBuilderPlugin());
        $o->registerPlugin(new GoogleMobileXmlSitemapBuilderPlugin());
        return $o;
    }


    private function newSitemap()
    {
        if (count($this->__sitemap->getUrls()) > 0) {
            $file = $this->getFileName($this->__curSlice, $this->__curSliceNb);
            $url = call_user_func($this->__urlCb, $file);
            $this->__sitemapIndexSitemap->setLoc($url);
            $this->__sitemapIndexSitemap->setLastmod(date('c'));
            $this->__sitemapIndex->addSitemap($this->__sitemapIndexSitemap);
            $this->__builder->createSitemapFile($this->__sitemap, $file);


            $this->__sitemap = Sitemap::create();
            $this->__sitemapIndexSitemap = SitemapIndexSitemap::create();


            $this->__curSliceNb++;
        }
    }

    private function getFileName(SitemapSliceInterface $slice, $curSlice)
    {
        $file = $slice->getFile();
        if (is_string($file)) {
            if (1 === $curSlice) {
                $file = str_replace('{n}', '', $file);
            }
            else {
                $file = str_replace('{n}', $curSlice, $file);
            }
        }
        elseif (is_callable($file)) {
            $file = call_user_func($file, $curSlice);
        }
        else {
            // or default value of sitemap{n}.xml
            if (1 === $curSlice) {
                $file = 'sitemap.xml';
            }
            else {
                $file = 'sitemap' . $curSlice . '.xml';
            }
        }
        return $file;
    }
}
