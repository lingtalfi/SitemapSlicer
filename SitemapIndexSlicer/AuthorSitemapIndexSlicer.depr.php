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

        $builder = $this->getSiteMapBuilder();


        $sitemapIndex = SitemapIndex::create();
        $urlCb = (is_callable($this->urlCb)) ? $this->urlCb : function ($filePath) {
            return 'http://dummy.com/' . basename($filePath);
        };


        $curSlice = 1;
        $nbUrlsProcessed = 0;
        foreach ($this->slices as $slice) {

            $sitemapIndexSitemap = SitemapIndexSitemap::create();
            $sitemapIndex->addSitemap($sitemapIndexSitemap);


            $sitemap = Sitemap::create();


            try {


                // define sliceWidth
                if (null === ($sliceWidth = $slice->getSliceWidth())) {
                    $sliceWidth = $this->_defaultSliceWidth;
                }


                // define file
                $file = $this->getFileName($slice, $curSlice);
                $this->prepareSitemapIndexSitemapEntry($sitemapIndexSitemap, $urlCb, $file);

                $bindures = $slice->getTableBindures();
                $hasBeenSliced = false;

                foreach ($bindures as $bIndex => $b) {

                    $tableOffset = 0;

                    $nbItems = (int)$b->getCount();
                    $rowsCb = $b->getRowsCallback();
                    $convert = $b->getConvertToSitemapEntryCallback();


                    $rows = call_user_func($rowsCb, $tableOffset, $sliceWidth);
                    if (is_array($rows)) {


                        foreach ($rows as $rIndex => $row) {

                            $url = call_user_func($convert, $row);
                            $sitemap->addUrl($url);
                            $tableOffset++;
                            $nbUrlsProcessed++;

                            // handling the slice overflow
                            if ($nbUrlsProcessed >= $sliceWidth) {


                                $nbItems -= $nbUrlsProcessed;
                                // there is more unprocessed items in the table
                                if ($nbItems > 0) {
                                    if (!array_key_exists($rIndex + 1, $rows)) {
                                        
                                        a("new slice");
                                        // make new slice
                                        $builder->createSitemapFile($sitemap, $file);                                        
                                        $rows = call_user_func($rowsCb, $tableOffset, $sliceWidth);
                                        
                                        
                                        $file = $this->getFileName($slice, $curSlice);
                                        $sitemapIndexSitemap = SitemapIndexSitemap::create();
                                        $sitemapIndex->addSitemap($sitemapIndexSitemap);
                                        $this->prepareSitemapIndexSitemapEntry($sitemapIndexSitemap, $urlCb, $file);



                                        $sitemap = Sitemap::create();
                                        
                                        
                                        
                                    }
                                    else{
                                        a("don't know this case");
                                    }
                                }


                                $nbUrlsProcessed = 0;
                                $hasBeenSliced = true;
                                $curSlice++;

                                if (
                                    $nbItems > 0 ||
                                    array_key_exists($rIndex + 1, $rows) ||
                                    array_key_exists($bIndex + 1, $bindures)
                                ) {
//                                    $file = $this->getFileName($slice, $curSlice);
//                                    a($file);
//                                    $sitemapIndexSitemap = SitemapIndexSitemap::create();
//                                    $sitemapIndex->addSitemap($sitemapIndexSitemap);
//                                    $this->prepareSitemapIndexSitemapEntry($sitemapIndexSitemap, $urlCb, $file);
//                                    $sitemap = Sitemap::create();
                                }
                            }

                        }

                    }
                    else {
                        $this->warning(sprintf("Invalid rows type, expected array, %s given", gettype($rows)));
                    }

                }

                if (false === $hasBeenSliced) {
                    $builder->createSitemapFile($sitemap, $file);
                }


            } catch (\Exception $e) {
                $this->warning((string)$e);
            }
            $curSlice++;
        }


        $builder->createSitemapIndexFile($sitemapIndex, $this->filePath);
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


    private function prepareSitemapIndexSitemapEntry(SitemapIndexSitemap $sitemapIndexSitemap, $urlCb, $file)
    {
        $url = call_user_func($urlCb, $file);
        $sitemapIndexSitemap->setLoc($url);
        $sitemapIndexSitemap->setLastmod(date('c'));
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
