<?php

use Huncwot\UhoFramework\_uho_view;

/**
 * Main (and only) view class of the application
 */

class view_app extends _uho_view
{

    /**
     * Util function replacing [[tags]]
     *
     * @param string $html 
     * @param string $id
     *
     * @return null|string[]
     *
     * @psalm-return list{string, string, string}|null
     */
    public function cut(string $html,string $id)
    {
        $i1=strpos($html,'<p>[['.$id.']]</p>');
        $i2=strpos($html,'<p>[[/'.$id.']]</p>',$i1);

        if ($i1!==false && $i2>$i1) return
          [
            substr($html,0,$i1),
            substr($html,$i1+11+strlen($id),$i2-$i1-strlen($id)-11),
            substr($html,$i2+12+strlen($id))
          ];
    }

    /**
     * Util function remvoving <p> from html
     *
     * @param string $cut 
     *
     * @return array
     *
     */
    public function p2array(string $cut): array
    {
        $d = explode('</p>',$cut);
        foreach ($d as $k=>$v)
        {
          $v=str_replace('<p>','',$v);
          $v=str_replace(chr(13),'',$v);
          $v=str_replace(chr(10),'',$v);
          if ($v) $d[$k]=$v; else unset($d[$k]);
        }
        return $d;

    }
    //=========================================================================================
    /*
    private function contentReplace($html,$start1,$start2,$end1,$end2)
    {

      while ($i1=strpos($html,$start1))
      {
       $i2=strpos($html,$end1,$i1);
       if (!$i2) $i2=strlen($html);
       $content=$start2.substr($html,$i1+strlen($start1),$i2-$i1-strlen($start1)).$end2;
       $html=substr($html,0,$i1).$content.substr($html,$i2+strlen($end1));
      }

      return $html;
    }
    */

    /**
     * Main method rendering HTML
     * @param array $data 
     * @return string
     */

    public function getHtml($data)
    {
      
      $data['content']=$this->getContentHtml($data['content'],$data['view']);

      // render whole page
      if ($this->renderHtmlRoot)
      {
        $html=$this->getTwig('',$data);
      }
      // render content only
      else
      {
        $html=$data['content'];
      }


      /*
        [[icon::slug]] handler
      */
      
      $nr=100;
      while (strpos(' '.$html,'[[icon::') && $nr>0)
      {
        $i=strpos($html,'[[icon::');
        $j=strpos($html,']]',$i);
        if ($j>$i)
        {
          $svg=substr($html,$i+8,$j-$i-8);
          $svg=explode(',',$svg);
          if ($svg[1]=='left') $class=' pull-left'; else $class='';
          $svg=$svg[0];
          $replace=['back'=>'keyboard-backspace','eye'=>'remove-red-eye'];
          if (!empty($replace[$svg])) $svg=$replace[$svg];
          $svg='<span class="mdi mdi-'.$svg.$class.'"></span>';
          $html=substr($html,0,$i).$svg.substr($html,$j+2);
          $nr--;
        } else $nr=0;
      }
      
      
      return $html;
    }





}
