<?php
namespace App\Controller;
use App;
use Swoole;

class Blog extends App\FrontPage
{
    function index()
    {

    }

    function rss()
    {
        if (!empty($_GET['id']))
        {
            $uid = (int)$_GET['id'];
            $user = App\Func::getUser($uid);
            $_mblog = createModel('MicroBlog');
            $_blog = createModel('UserLogs');
    		$gets['uid'] = $uid;
    		$gets['select'] = 'id,content,addtime';
    		$gets['limit'] = 10;
    		$mblogs = $_mblog->gets($gets);

    		foreach($mblogs as &$v)
    		{
    			$v['title'] = strip_tags(App\Func::mblog_link($v['id'],$v['content'],30,true));
    			$v['content'] = nl2br($v['content']);
    			$v['url'] = WEBROOT.'/mblog/detail/'.$v['id'];
    		}
    		$gets['uid'] = $uid;
    		$gets['select'] = 'id,title,content,addtime';
    		$gets['limit'] = 10;
			$gets['dir'] = 0;
    		$blogs = $_blog->gets($gets);
            $list = array_merge($mblogs, $blogs);
            usort($list, 'App\Func::time_sort');
            foreach ($list as &$v)
            {
                $v['addtime'] = date('r', strtotime($v['addtime']));
                if (empty($v['url']))
                {
                    $v['url'] = WEBROOT . '/blog/detail/' . $v['id'];
                }
            }
    		$this->swoole->tpl->ref('user',$user);
    		$this->swoole->tpl->ref('list',$list);
    		$this->swoole->tpl->display('blog_rss.xml');
    	}
        else
        {
            $this->http->status(403);
            return "错误的请求";
        }
    }

    function detail()
    {
        $id = (int)$_GET['id'];
        $_blog = createModel('UserLogs');
        $blog = $_blog->get($id);
        if(empty($_COOKIE['look']) and $_COOKIE['look']!=$id)
        {
            $blog->look_count++;
            $blog->save();
            Swoole\Cookie::set('look', $id, time()+3600);
        }

        $uid = $blog['uid'];
        $this->userinfo($uid);
        $_c = createModel('UserComment');
        $comments = $_c->getByAid('blog', $id);
        $this->swoole->tpl->assign('comments',$comments);
        $this->swoole->tpl->assign('blog', $blog->get());
        return $this->swoole->tpl->fetch('blog_detail.html');
    }

    function category()
    {
        if(empty($_GET['cid']) and empty($_GET['user'])) error(409);
		
        $_blog = createModel('UserLogs');
        $_cate = createModel('UserLogCat');
		$gets1 = array();
		if(isset($_GET['cid']))
		{
			$cid = (int)$_GET['cid'];
			$cate = $_cate->get($cid)->get();
			$uid = $cate['uid'];
			$gets1['c_id'] = $cid;
		}
        else
		{
			$uid = (int)$_GET['user'];
			$cate = array();
		}
        $this->userinfo($uid);
        $gets1['uid'] = $uid;
        $gets1['dir'] = 0;
        $gets1['select'] = 'title,id,substring(content,1,1000) as des,addtime,reply_count,look_count';
        $gets1['page'] = empty($_GET['page'])?1:(int)$_GET['page'];
        $gets1['pagesize'] = 10;
        $blogs = $_blog->gets($gets1,$pager);
        foreach($blogs as &$m)
        {
            $m['addtime'] = date('n月j日 H:i',strtotime($m['addtime']));
            $m['des'] = mb_substr(strip_tags($m['des']),0,120);
        }
        $this->swoole->tpl->assign('cate',$cate);
        $this->swoole->tpl->assign('blogs',$blogs);
        $pager->span_open = array();
        $pager = array('total'=>$pager->total,'render'=>$pager->render());
        $this->swoole->tpl->assign('pager',$pager);
        return $this->swoole->tpl->fetch('blog_category.html');
    }
}