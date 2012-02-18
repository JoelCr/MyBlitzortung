<?php

/*
    MyBlitzortung - a tool for participants of blitzortung.org
	to display lightning data on their web sites.

    Copyright (C) 2011  Tobias Volgnandt

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if (!defined('BO_VER'))
	exit('No BO_VER');

// Graph from raw dataset
function bo_graph_raw($id, $spec = false)
{
	if (!file_exists(BO_DIR.'includes/jpgraph/jpgraph.php'))
		bo_graph_error(BO_GRAPH_RAW_W, BO_GRAPH_RAW_H);

	bo_session_close();
		
	$id = intval($id);

	$sql = "SELECT id, time, time_ns, lat, lon, height, data
			FROM ".BO_DB_PREF."raw
			WHERE id='$id'";
	$erg = bo_db($sql);
	
	if (!$erg->num_rows)
	{
		$I = imagecreate(BO_GRAPH_RAW_W,BO_GRAPH_RAW_H);
		$color = imagecolorallocate($I, 255, 150, 150);
		imagefill($I, 0, 0, $color);
		imagecolortransparent($I, $color);
		Header("Content-type: image/png");
		Imagepng($I);
		exit;
	}
	
	$row = $erg->fetch_assoc();

	require_once 'jpgraph/jpgraph.php';
	require_once 'jpgraph/jpgraph_line.php';
	require_once 'jpgraph/jpgraph_bar.php';
	require_once 'jpgraph/jpgraph_plotline.php';
	
	$tickLabels = array();
	$tickMajPositions = array();
	$tickPositions = array();

	$channels = BO_ANTENNAS;
	
	$graph = new Graph(BO_GRAPH_RAW_W,BO_GRAPH_RAW_H,"auto");
	$graph->ClearTheme();

	if (defined("BO_GRAPH_ANTIALIAS") && BO_GRAPH_ANTIALIAS)
		$graph->img->SetAntiAliasing();

	if (BO_GRAPH_RAW_COLOR_BACK)
		$graph->SetColor(BO_GRAPH_RAW_COLOR_BACK);

	if (BO_GRAPH_RAW_COLOR_BACK)
		$graph->SetMarginColor(BO_GRAPH_RAW_COLOR_MARGIN);

	if (BO_GRAPH_RAW_COLOR_FRAME)
		$graph->SetFrame(true, BO_GRAPH_RAW_COLOR_FRAME);
	else
		$graph->SetFrame(false);

	if (BO_GRAPH_RAW_COLOR_BOX)
		$graph->SetBox(true, BO_GRAPH_RAW_COLOR_BOX);
	else
		$graph->SetBox(false);

	$graph->SetMargin(24,1,1,1);

	$fullscale = isset($_GET['full']);
	
	if ($spec)
	{
		$data = raw2array($row['data'], true);
		$step = 5;
		
		foreach ($data['spec_freq'] as $i => $khz)
		{
			$tickLabels[$i] = (round($khz / 5) * 5).'kHz';
		}

		$utime    = bo_get_conf('raw_ntime') / 1000;
		$values   = bo_get_conf('raw_values');
		
		$graph->SetScale("textlin", 0, $fullscale ? null : BO_GRAPH_RAW_SPEC_MAX_Y, 0, BO_GRAPH_RAW_SPEC_MAX_X * $values * $utime / 1000);

		
		$plot1=new BarPlot($data['spec'][0]);
		$plot1->SetFillColor(BO_GRAPH_RAW_COLOR1);
		$plot1->SetColor('#fff@1');
		$plot1->SetAbsWidth(BO_GRAPH_RAW_SPEC_WIDTH / 2);

		$plot2=new BarPlot($data['spec'][1]);
		$plot2->SetFillColor(BO_GRAPH_RAW_COLOR2);
		$plot2->SetColor('#fff@1');
		$plot2->SetAbsWidth(BO_GRAPH_RAW_SPEC_WIDTH / 2);

		$plot = new GroupBarPlot(array($plot1, $plot2));
		$graph->Add($plot);

		
		if (BO_GRAPH_RAW_COLOR_XGRID)
		{
			$graph->xgrid->SetColor(BO_GRAPH_RAW_COLOR_XGRID);
			$graph->xgrid->Show(true,true);
		}
		else
			$graph->xgrid->Show(false);

		if (BO_GRAPH_RAW_COLOR_YGRID)
		{
			$graph->ygrid->SetColor(BO_GRAPH_RAW_COLOR_YGRID);
			$graph->ygrid->Show(true,true);
		}
		else
			$graph->ygrid->Show(false,false);
		
		$graph->xaxis->SetColor(BO_GRAPH_RAW_COLOR_XAXIS);
		$graph->yaxis->SetColor(BO_GRAPH_RAW_COLOR_YAXIS);
		$graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
		$graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
		$graph->yaxis->HideLabels();
		
		$graph->xaxis->SetTickLabels($tickLabels);
		$graph->xaxis->SetTextLabelInterval(2);
		$graph->xaxis->SetTextTickInterval(2);
		
	}
	else
	{
		$data = raw2array($row['data']);
		
		$ustep = 50;
		foreach ($data['signal_time'] as $i => $time_us)
		{
			$datax[] = $i;
			$time_us = round($time_us / $ustep, 1) * $ustep;

			if (!($i%12))
			{
				if (!($i%18))
				{
					$tickMajPositions[] = $i;
					$tickLabels[] = $time_us.'�s';
				}
				elseif (!($i%6))
				{
					$tickPositions[] = $i;
				}
			}
		}

		
		$n = count($datax);
		$xmin = $datax[0];
		$xmax = $datax[$n-1];

		if ($fullscale)
		{
			$ymax = $ymin = null;
		}
		else
		{
			$ymin = -BO_MAX_VOLTAGE;
			$ymax = BO_MAX_VOLTAGE;
		}
		
		$graph->SetScale("linlin",$ymin,$ymax,$xmin,$xmax);
		
		if (max($data['signal'][0]) || min($data['signal'][0]))
		{
			$plot=new LinePlot($data['signal'][0], $datax);
			$plot->SetColor(BO_GRAPH_RAW_COLOR1);
			$graph->Add($plot);
		}

		if (max($data['signal'][1]) || min($data['signal'][1]))
		{
			$plot=new LinePlot($data['signal'][1], $datax);
			$plot->SetColor(BO_GRAPH_RAW_COLOR2);
			$graph->Add($plot);
		}
		
		$graph->xaxis->SetPos('min');
		$graph->xaxis->SetTickPositions($tickMajPositions,$tickPositions,$tickLabels);

		$graph->xaxis->SetColor(BO_GRAPH_RAW_COLOR_XAXIS);
		$graph->yaxis->SetColor(BO_GRAPH_RAW_COLOR_YAXIS);

		if (BO_GRAPH_RAW_COLOR_XGRID)
		{
			$graph->xgrid->SetColor(BO_GRAPH_RAW_COLOR_XGRID);
			$graph->xgrid->Show(true,true);
		}
		else
			$graph->xgrid->Show(false);

		if (BO_GRAPH_RAW_COLOR_YGRID)
		{
			$graph->ygrid->SetColor(BO_GRAPH_RAW_COLOR_YGRID);
			$graph->ygrid->Show(true,true);
		}
		else
			$graph->ygrid->Show(false,false);

		if (!$fullscale)
		{
			$graph->yaxis->SetTextTickInterval(0.5);

			for($i=-BO_MAX_VOLTAGE;$i<=BO_MAX_VOLTAGE;$i+=0.5)
			{
				if (abs($i) != 0.5)
					$yt[] = $i;
			}

			$graph->yaxis->SetTickPositions(array(-2,-1,0,1,2),$yt,array('-2V','-1V','0V','1V','2V'));
		}
		
		$sline  = new PlotLine(HORIZONTAL,  BO_TRIGGER_VOLTAGE, BO_GRAPH_RAW_COLOR3, 1);
		$graph->AddLine($sline);

		$sline  = new PlotLine(HORIZONTAL, -BO_TRIGGER_VOLTAGE, BO_GRAPH_RAW_COLOR3, 1);
		$graph->AddLine($sline);

		$graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
		$graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);
	}

	
	BoDb::close();
	bo_session_close(true);
	
	$time = strtotime($row['time'].' UTC');
	
	header("Content-Type: image/png");
	header("Pragma: ");
	header("Cache-Control: public, max-age=".(3600 * 24));
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $time)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $time + 3600 * 24)." GMT");

	$I = $graph->Stroke(_IMG_HANDLER);
	imagepng($I);
	
	exit;
}

function bo_graph_statistics($type = 'strikes', $station_id = 0, $hours_back = null)
{
	global $_BO;
	
	if (!file_exists(BO_DIR.'includes/jpgraph/jpgraph.php'))
		bo_graph_error(BO_GRAPH_STAT_W, BO_GRAPH_STAT_H);

	bo_session_close();

	if (!$hours_back)
	{
		if ($type == 'stations')
			$hours_back = intval(BO_GRAPH_STAT_HOURS_BACK_STATIONS) + (int)date('H');
		else
			$hours_back = intval(BO_GRAPH_STAT_HOURS_BACK);
	}
	
	$hours_back = intval($hours_back) ? intval($hours_back) : 24;
	$hours_back = !bo_user_get_level() && $hours_back > 96 ? 96 : $hours_back;
	$stId = bo_station_id();

	$group_minutes = intval($_GET['group_minutes']);
	if ($group_minutes < BO_GRAPH_STAT_STRIKES_ADV_GROUP_MINUTES)
		$group_minutes = BO_GRAPH_STAT_STRIKES_ADV_GROUP_MINUTES;
	
	$date_end = gmdate('Y-m-d H:i:s', bo_get_conf('uptime_stations'));
	$time_end = strtotime($date_end." UTC");
	$date_start = gmdate('Y-m-d H:i:s', time() - 3600 * $hours_back);
	$time_start = strtotime($date_start." UTC");

	$X = $Y = $Y2 = $Y3 = array(); //data array
	$tickLabels = array();
	$tickMajPositions = array();
	$tickPositions = array();
	$xmin = null;
	$xmax = null;
	$ymin = null;
	$ymax = null;
	$add_title = '';

	//2nd type
	$type2 = $_GET['type2'];
	
	//Value
	$value = isset($_GET['value']) ? intval($_GET['value']) : 0;
	
	//Region
	$region = $_GET['region'];
	
	if ($_BO['region'][$region]['name'])
		$add_title .= ' ('._BL($_BO['region'][$region]['name'], true).')';

	//Channel
	$channel = intval($_GET['channel']);
	
	if ($channel)
	{
		$sql_part = ' ( (s.part&'.(1<<$channel).')>0 AND s.part>0  ) ';
		$add_title .= ' ('._BL('Channel', true).' '.($channel).')';
	}
	else
	{
		$sql_part = ' (s.part>0) ';
	}
	
	if ($type == 'strikes_now')
	{
		$last_uptime = bo_get_conf('uptime_strikes');
		$time_max = time();
		
		$group_minutes = intval($_GET['group_minutes']);
		if ($group_minutes < BO_GRAPH_STAT_STRIKES_NOW_GROUP_MINUTES)
			$group_minutes = BO_GRAPH_STAT_STRIKES_NOW_GROUP_MINUTES;

		if ($hours_back > 24)
			$group_minutes *= ceil($hours_back / 24);
		
		$time_max = floor($time_max / 60 / $group_minutes) * 60 * $group_minutes; //round
		
		if ($station_id)
		{
			$sql_select = " , ss.station_id IS NOT NULL participated ";
			$sql_join   = "LEFT JOIN ".BO_DB_PREF."stations_strikes ss
							ON s.id=ss.strike_id AND ss.station_id='$station_id'";
		}
		else
		{
			$sql_select = ", $sql_part participated";
			$sql_join   = "";
		}
		
		$sql = "SELECT s.time time, COUNT(s.id) cnt $sql_select
			FROM ".BO_DB_PREF."strikes s
			$sql_join
			WHERE s.time BETWEEN '".gmdate('Y-m-d H:i:s', $time_max - 3600 * $hours_back)."' AND '".gmdate('Y-m-d H:i:s', $time_max)."'
			".bo_region2sql($region)."
			GROUP BY FLOOR(UNIX_TIMESTAMP(s.time) / 60 / $group_minutes), participated";
		$res = bo_db($sql);
		while ($row = $res->fetch_assoc())
		{
			$time = strtotime($row['time'].' UTC');
			$index = floor( ($time - $time_max + $hours_back * 3600) / 60 / $group_minutes);
			
			$tmp[$index][$row['participated']] = $row['cnt'] / $group_minutes;
		}
		
		for ($i = 0; $i < $hours_back * 60 / $group_minutes; $i++)
		{
			$X[$i] = $time_max + ($i * $group_minutes - $hours_back * 60) * 60;
			$Y[$i] = (double)($tmp[$i][0] + $tmp[$i][1]);
			$Y2[$i] = (double)$tmp[$i][1];
		}

		$graph_type = 'datlin';
		
		$caption  = (array_sum($Y) * $group_minutes).' '._BL('total strikes');
		$caption .= "\n";
		$caption .= (array_sum($Y2) * $group_minutes).' '._BL('total strikes station2');
	}
	else if ($type == 'strikes_time')
	{
		$station_id = 0;
		
		$year = intval($_GET['year']);
		$month = intval($_GET['month']);
		$radius = intval($_GET['radius']);
		
		if ($radius)
		{
			$rad = 2;
			$add_title .= ' '.strtr(_BL('_in_radius'), array('{RADIUS}' => BO_RADIUS_STAT));
		}

		if ($month == -1)
		{
			$like = 'strikes_'.$year.'%';
			$time_begin = strtotime("$year-01-01");
			$days = date('L', $time_begin) ? 366 : 365;

			$xtitle = 'Month';
			$add_title .= ' '.$year;
		}
		else
		{
			$like = 'strikes_'.$year.sprintf('%02d', $month).'%';
			$time_begin = strtotime("$year-$month-01");
			$days = date('t', $time_begin);

			$xtitle = 'Day';
			$add_title .= ' '._BL(date('F', $time_begin)).' '.$year;
		}
		
		$day_offset = date('z', $time_begin);

		for ($i=0;$i<$days;$i++)
		{
			$time = mktime(0,0,0,$month == -1 ? 1 : $month, $i+1, $year);

			if ($month == -1 && date('d', $time) == 1)
			{
				$tickLabels[] = _BL(date('M', $time));
				$tickMajPositions[] = $i;
			}
			else if ($month != -1 && !($i%5))
			{
				$tickLabels[] = date('d.m', $time);
				$tickMajPositions[] = $i;
			}
			
			$Y[$i]  = 0;
			$Y2[$i] = 0;
			$Y3[$i] = 0;
		}

		$sql = "SELECT DISTINCT SUBSTRING(name, 9) time, data, changed
				FROM ".BO_DB_PREF."conf
				WHERE name LIKE '$like'
				ORDER BY time";
		$res = bo_db($sql);
		while($row = $res->fetch_assoc())
		{
			$y = substr($row['time'], 0, 4);
			$m = substr($row['time'], 4, 2);
			$d = substr($row['time'], 6, 2);
			$time = strtotime("$y-$m-$d");
			$i = date('z', $time) - $day_offset;
			$d = unserialize($row['data']);

			if ($region)
			{
				$Y[$i] =  $d[7][$region]['own']; //participanted
				$Y2[$i] = $d[7][$region]['all'] - $d[7][$region]['own']; //other
				$Y3[$i] = $d[7][$region]['all']; //Sum
			}
			else
			{
				$Y[$i] = $d[1 + $rad]; //participanted
				$Y2[$i] = $d[0 + $rad] - $d[1 + $rad]; //other
				$Y3[$i] = $d[0 + $rad]; //Sum
			}
		}

		
		//count for today
		if ($year == gmdate('Y') && ($month == -1 || $month == gmdate('m')))
		{
			$i = gmdate('z') - $day_offset;
			$sql = "SELECT COUNT(id) cnt, $sql_part participated
						FROM ".BO_DB_PREF."strikes s
						WHERE time BETWEEN '".gmdate('Y-m-d 00:00:00')."' AND '".gmdate('Y-m-d 23:59:59')."'
						".($radius ? " AND distance < '".(BO_RADIUS_STAT * 1000)."'" : "")."
						GROUP BY participated";
			$res = bo_db($sql);
			while($row = $res->fetch_assoc())
			{
				if ($row['participated'])
					$Y[$i] = $row['cnt'];
				else
					$Y2[$i] = $row['cnt'];
				
				$Y3[$i] += $row['cnt'];
			}
		}
		
		$caption  = (array_sum($Y) + array_sum($Y2)).' '._BL('total strikes');
		$caption .= "\n";
		$caption .= array_sum($Y).' '._BL('total strikes station');
		
		$title_no_hours = true;
		
		$ymin = 0;
		$ymax = max(10, max($Y3) * 1.2);
		
		$graph_type = 'textlin';
	}
	else if ($type == 'ratio_distance_longtime')
	{
		if (BO_ENABLE_LONGTIME_ALL !== true)
			$station_id = 0;
			
		if ($station_id > 0 && $station_id != bo_station_id())
			$add = '#'.$station_id.'#';
		else
			$add = '';
		
		$own = unserialize(bo_get_conf('longtime_dist_own'.$add));
		$all = unserialize(bo_get_conf('longtime_dist'.$add));

		if (is_array($own) && is_array($all))
		{
			foreach($own as $dist => $cnt)
			{
				if ($cnt < 3 || !is_numeric($dist)) //don't display ratios with low strike counts
					continue;
					
				$X[$dist] = $dist * 10;

				if ($all[$dist])
					$Y[$dist] = $cnt / $all[$dist] * 100;
				else
					$Y[$dist] = null;

				$max_dist = max($max_dist, $dist);
			}

			foreach($all as $dist => $cnt)
			{
				if (is_numeric($dist))
				{
					$Y2[$dist] = $cnt;
					$max_dist = max($max_dist, $dist);
				}
			}

			for ($i=0;$i<=$max_dist;$i++)
			{
				$Y[$i] = isset($Y[$i]) ? $Y[$i] : null;
				$Y2[$i] = isset($Y2[$i]) ? $Y2[$i] : null;
				
				if ( !($i%5))
					$tickPositions[] = $i;

				if ( !($i%50))
				{
					$tickLabels[] = $i * 10;
					$tickMajPositions[] = $i;
				}
				
				
			}
			
		}

		$graph_type = 'textlin';
		$title_no_hours = true;
		
		if (isset($own['time']) && $own['time'] && $station_id != 0)
			$add_title = ' '._BL('since').' '.date(_BL('_date'),$own['time']);
		else
			$add_title = ' '._BL('since begin of data logging');
		
		$ymin = 0;
		$ymax = 100;
	}
	else if($type == 'ratio_bearing_longtime')
	{
		if (BO_ENABLE_LONGTIME_ALL !== true)
			$station_id = 0;
			
		if ($station_id > 0 && $station_id != bo_station_id())
			$add = '#'.$station_id.'#';
		else
			$add = '';

		$bear_div = 1;
		
		$own = unserialize(bo_get_conf('longtime_bear_own'.$add));
		$all = unserialize(bo_get_conf('longtime_bear'.$add));
		
		if (is_array($own) && is_array($all))
		{
			foreach($own as $bear => $cnt)
			{
				if ($cnt < 3 || !is_numeric($bear)) //don't display ratios with low strike counts
					continue;

				$X[$bear] = $bear * 10;

				if ($all[$bear])
					$Y[$bear] = $cnt / $all[$bear] * 100;
				else
					$Y[$bear] = null;
			}

			foreach($all as $bear => $cnt)
			{
				if (is_numeric($bear))
					$Y2[$bear] = $cnt;
			}

			for ($i=0;$i<360;$i++)
			{
				$Y[$i] = isset($Y[$i]) ? $Y[$i] : null;
				$Y2[$i] = isset($Y2[$i]) ? $Y2[$i] : null;
				
				if ( !($i%5))
					$tickPositions[] = $i;

				if ( !($i%45))
				{
					$tickLabels[] = $i;
					$tickMajPositions[] = $i;
				}
				
			}

		}


		if (isset($own['time']) && $own['time'] && $station_id != 0)
			$add_title = ' '._BL('since').' '.date(_BL('_date'),$own['time']);
		else
			$add_title = ' '._BL('since begin of data logging');

		$graph_type = 'linlin';
		$title_no_hours = true;
		$xmin = 0;
		$xmax = 360;
		$ymin = 0;
		$ymax = 100;
	}
	else if ($type == 'ratio_distance' || $type == 'ratio_bearing')
	{
		$dist_div = BO_GRAPH_STAT_RATIO_DIST_DIV; //interval in km
		$bear_div = BO_GRAPH_STAT_RATIO_BEAR_DIV;

		
		$xmin = 0;
		if ($type == 'ratio_bearing')
			$xmax = floor(360 / $bear_div) -1 ;
		
		$tmp = array();
		$ticks = 0;
		if ($station_id) //Special Query for own "ratio strikes by distance" - may be slow!
		{
			$station_info = bo_station_info($station_id);
			$stLat = $station_info['lat'];
			$stLon = $station_info['lon'];

			$sql = "SELECT s.lat lat, s.lon lon, ss.station_id stid
					FROM ".BO_DB_PREF."strikes s
					LEFT JOIN ".BO_DB_PREF."stations_strikes ss
						ON s.id=ss.strike_id AND ss.station_id='$station_id'
					WHERE s.time BETWEEN '$date_start' AND '$date_end'
					".bo_region2sql($region)."
					";
			$res = bo_db($sql);
			while($row = $res->fetch_assoc())
			{
				if ($type == 'ratio_bearing')
					$val = floor((bo_latlon2bearing($row['lat'], $row['lon'], $stLat, $stLon)%360) / $bear_div);
				else
					$val = floor(bo_latlon2dist($row['lat'], $row['lon'], $stLat, $stLon) / $dist_div / 1000);

				$part = $row['stid'] ? 1 : 0;
				$tmp[$part][$val] += 1;
				$ticks = max($ticks, $val);
				$x++;
			}
		}
		else
		{
			if ($type == 'ratio_bearing')
				$sql = " FLOOR((bearing%360)/$bear_div) val ";
			else
				$sql = " FLOOR(distance/$dist_div/1000) val ";

			//strike ratio for own station
			$sql = "SELECT COUNT(id) cnt, $sql_part participated, $sql
					FROM ".BO_DB_PREF."strikes s
					WHERE time BETWEEN '$date_start' AND '$date_end'
					".bo_region2sql($region)."
					GROUP BY participated, val";
			$res = bo_db($sql);
			while($row = $res->fetch_assoc())
			{
				$tmp[$row['participated']][$row['val']] = $row['cnt'];
				$x += $row['cnt'];
				$ticks = max($ticks, $row['val']);
			}
		}
		
		if ($type == 'ratio_bearing')
			$ticks = 360 / $bear_div;

		for($i=0;$i<$ticks;$i++)
		{
			if ($type == 'ratio_bearing')
			{
				if ( !(($i*$bear_div)%45))
				{
					$tickLabels[] = $i*$bear_div;
					$tickMajPositions[] = $i;
				}
			}
			else
			{
				if ( !(($i*$dist_div)%500))
				{
					$tickLabels[] = $i*$dist_div;
					$tickMajPositions[] = $i;
				}
			}
			
			if ($tmp[0][$i])
				$Y[$i] = $tmp[1][$i] / ($tmp[0][$i]+$tmp[1][$i]) * 100;
			else
				$Y[$i] = 0;

			$Y2[$i] = intval($tmp[0][$i]+$tmp[1][$i]);
		}

		$graph_type = 'textlin';
		
		if (count($tickMajPositions) < 2)
		{
			$tickLabels[] = $i*$dist_div;
			$tickMajPositions[] = $i;
		}
		
		$ymin = 0;
		$ymax = 100;
	}
	else if ($type == 'distance')
	{
		//Mean strike distance to station by time of all strikes and own strikes
		
		$station_id = 0;
		
		$interval = $hours_back / 24 * $group_minutes;
		$ticks = ($time_end - $time_start) / 60 / $interval;

		$tmp = array();
		
		$sql = "SELECT SUM(distance) sum_dist, COUNT(distance) cnt_dist, $sql_part participated, time
				FROM ".BO_DB_PREF."strikes s
				WHERE time BETWEEN '$date_start' AND '$date_end'
				".bo_region2sql($region)."
				GROUP BY participated, DAYOFMONTH(time), HOUR(time), FLOOR(MINUTE(time) / ".$interval.")";
		$res = bo_db($sql);
		while($row = $res->fetch_assoc())
		{
			$time = strtotime($row['time'].' UTC');
			$index = floor( ($time - time() + 3600 * $hours_back) / 60 / $interval);

			if ($index < 0)
				continue;

			$tmp['all_sum'][$index] += $row['sum_dist'];  //distance sum
			$tmp['all_cnt'][$index] += $row['cnt_dist'];  //distance count
			
			if ($row['participated'])
			{
				$tmp['own_sum'][$index] += $row['sum_dist'];  //own distance sum
				$tmp['own_cnt'][$index] += $row['cnt_dist'];  //own distance count
			}
		}

		for($i=0;$i<$ticks-1;$i++)
		{
			$X[$i] = $time_start + $i * $interval * 60;
			$Y[$i] = 0;
			$Y2[$i] = 0;
			
			if (intval($tmp['all_cnt'][$i]) != 0)
				$Y[$i] = $tmp['all_sum'][$i] / $tmp['all_cnt'][$i] / 1000;

			if (intval($tmp['own_cnt'][$i]) != 0)
				$Y2[$i] = $tmp['own_sum'][$i] / $tmp['own_cnt'][$i] / 1000;

		}
		
		$graph_type = 'datlin';
		
	}
	else if ($type == 'participants' || $type == 'deviations')
	{
		$interval = $hours_back / 24 * 15;
	
		switch($type)
		{
			case 'participants':
				$groupby = "s.users";
				$xmin = bo_participants_locating_min();
				$is_logarithmic = BO_GRAPH_STAT_PARTICIPANTS_LOG === true;
				break;

			case 'deviations':
				$groupby = " ROUND(s.deviation / 100)";
				$xmin = 0;
				$is_logarithmic = BO_GRAPH_STAT_DEVIATIONS_LOG === true;
				break;
		}
			
		
		if ($station_id)
		{
			$sql = "SELECT COUNT(s.id) cnt, ss.station_id spart, $groupby groupby
					FROM ".BO_DB_PREF."strikes s
					LEFT JOIN ".BO_DB_PREF."stations_strikes ss
					ON s.id=ss.strike_id AND ss.station_id='$station_id'
					WHERE time BETWEEN '$date_start' AND '$date_end'
					".bo_region2sql($region)."
					GROUP BY spart, groupby";
		}
		else
		{
			$sql = "SELECT COUNT(s.id) cnt, $sql_part spart, $groupby groupby
					FROM ".BO_DB_PREF."strikes s
					WHERE s.time BETWEEN '$date_start' AND '$date_end'
					".bo_region2sql($region)."
					GROUP BY spart, groupby";
		}
		
		$tmp = array();
		$res = bo_db($sql);
		while($row = $res->fetch_assoc())
		{
			$index = $row['groupby'];
			$xmax = max($row['groupby'], $max);
			$xmin = min($row['groupby'], $xmin);
			
			if ($row['spart'])
				$tmp['own'][$index] = $row['cnt'];
			else
				$tmp['all'][$index] = $row['cnt'];
		}

		for($i=0;$i<$xmax;$i++)
		{
			switch($type)
			{
				case 'participants':
					$X[$i] = $i;				
					break;

				case 'deviations':
					$tickLabels[$i] = number_format($i/10, 1, _BL('.'), _BL(',')).'km';
					$X[$i] = $i/10;
					break;
			}
			
			$Y[$i] = intval($tmp['own'][$i]);
			$Y2[$i] = intval($tmp['all'][$i]);
			
			if ($is_logarithmic)
			{
				$Y[$i] = $Y[$i] ? $Y[$i] + 0.1 : $Y[$i];
				$Y2[$i] = $Y2[$i] ? $Y2[$i] + 0.1 : $Y2[$i];
			}
			
			if ($Y[$i] + $Y2[$i])
				$Y3[$i] = $Y[$i] / ($Y[$i] + $Y2[$i]) * 100;
			else
				$Y3[$i] = null;
			
			$max = max($ymax, $Y[$i] + $Y2[$i]);
		}
	
		if ($is_logarithmic)
		{
			$graph_type = 'linlog';
		}
		else
		{
			$graph_type = 'linlin';
		}
		
		$tickLabels[] = '';
	}
	else if ($type == 'participants_time' || $type == 'deviations_time')
	{
		$value_max = isset($_GET['value_max']) ? intval($_GET['value_max']) : 0;
		$average = isset($_GET['average']);

		switch($type)
		{
			case 'participants_time':

				if (!$value)
					$value = bo_participants_locating_min();

				if (!$value_max || $value_max <= $value)
					$value_max = 0;

				break;

			case 'deviations_time':
			
				if (!$value)
					$value = 1;
				
				if (!$value_max || $value_max <= $value)
					$value_max = $value + 1000;

				break;
				
		}
			
		$last_uptime = bo_get_conf('uptime_strikes');
		$time_max = time();
		if ($hours_back > 24)
			$group_minutes *= ceil($hours_back / 24);
		
		$time_max = floor($time_max / 60 / $group_minutes) * 60 * $group_minutes; //round
		
		if ($station_id)
		{
			$sql_select = " , ss.station_id IS NOT NULL participated ";
			$sql_join   = "LEFT JOIN ".BO_DB_PREF."stations_strikes ss
							ON s.id=ss.strike_id AND ss.station_id='$station_id'";
		}
		else
		{
			$sql_select = " , $sql_part participated ";
			$sql_join   = "";
		}
		
		switch($type)
		{
			case 'participants_time':
				
				if ($average)
				{
					$participants_text = '';
					$sql_select .= ", 0 extra, SUM(s.users) extra_sum ";
				
				}
				else if ($value_max)
				{
					$participants_text = $value.'-'.$value_max;
					$sql_select .= ", s.users BETWEEN '$value' AND '$value_max' extra ";
				}
				else
				{
					$participants_text = $value;
					$sql_select .= ", s.users='$value' extra ";
				}
				
				break;

				
			case 'deviations_time':
				
				if ($average)
				{
					$participants_text = '';
					$sql_select .= ", 0 extra, SUM(s.deviation) extra_sum ";
				}
				else if ($value_max)
				{
					$deviations_text = number_format($value / 1000, 1, _BL('.'), _BL(',')).'-'.number_format($value_max / 1000, 1, _BL('.'), _BL(',')).'km';
					$sql_select .= ", s.deviation BETWEEN '$value' AND '$value_max' extra ";
				}
				else
				{
					$deviations_text = number_format($value / 1000, 1, _BL('.'), _BL(',')).'km';
					$sql_select .= ", s.deviation='$value' extra ";
				}
				
				break;
				
		}
		
		$sql = "SELECT s.time time, COUNT(s.id) cnt $sql_select
				FROM ".BO_DB_PREF."strikes s
				$sql_join
				WHERE s.time BETWEEN '".gmdate('Y-m-d H:i:s', $time_max - 3600 * $hours_back)."' AND '".gmdate('Y-m-d H:i:s', $time_max)."'
				".bo_region2sql($region)."
				GROUP BY FLOOR(UNIX_TIMESTAMP(s.time) / 60 / $group_minutes), participated, extra";
		$res = bo_db($sql);
		while ($row = $res->fetch_assoc())
		{
			$time = strtotime($row['time'].' UTC');
			$index = floor( ($time - $time_max + $hours_back * 3600) / 60 / $group_minutes);
			$tmp[$index][$row['participated']][$row['extra']] = $row['cnt'];
			
			if ($average)
				$extra_sum[$index][$row['participated']] = $row['extra_sum'];
		}

		$count_own = 0;
		$count_all = 0;
		
		for ($i = 0; $i < $hours_back * 60 / $group_minutes; $i++)
		{
			$X[$i] = $time_max + ($i * $group_minutes - $hours_back * 60) * 60;
			
			$all_all   = (double)(@array_sum($tmp[$i][0]) + @array_sum($tmp[$i][1]));
			$all_users = (double)($tmp[$i][0][1] + $tmp[$i][1][1]);
			$own_all   = (double)(@array_sum($tmp[$i][1]));
			$own_users = (double)$tmp[$i][1][1];
			
			$count_own += $own_all;
			$count_all += $all_all;
			
			if ($average)
			{
				$Y[$i]  = $all_all ? (double)(@array_sum($extra_sum[$i]) / $all_all) : 0;
				$Y2[$i] = $own_all ? (double)($extra_sum[$i][1] / $own_all) : 0;
			}
			else
			{
				$Y[$i]  = $all_all ? $all_users / $all_all * 100 : 0;
				$Y2[$i] = $own_all ? $own_users / $own_all * 100 : 0;
			}
			
			$Y3[$i] = $all_all;
		}
		
		$graph_type = 'datlin';
		
		$caption  = $count_all.' '._BL('total strikes');
		$caption .= "\n";
		$caption .= $count_own.' '._BL('total strikes station2');
		
		if (!$average)
		{
			$ymin = 0;
			$ymax = 105;
		}
		else
			$type .= '_average';
		
	}
	else if ($type == 'evaluated_signals')
	{
		$station_id = 0;
		if ($hours_back > 24)
			$group_minutes *= ceil($hours_back / 24);

		$last_uptime = bo_get_conf('uptime_raw'); //RAW-update time!!!!			
		$time_max = time();
		$time_max = floor($time_max / 60 / $group_minutes) * 60 * $group_minutes; //round
		
		
		$sql = "SELECT s.time time, COUNT(s.id) cnt, SIGN(".($channel ? ' ((s.part&'.(1<<$channel).')>0)*s.part' : 's.part').") participated
				FROM ".BO_DB_PREF."strikes s
				WHERE s.time BETWEEN '".gmdate('Y-m-d H:i:s', $time_max - 3600 * $hours_back)."' AND '".gmdate('Y-m-d H:i:s', $time_max)."'
				".bo_region2sql($region)."
				GROUP BY FLOOR(UNIX_TIMESTAMP(s.time) / 60 / $group_minutes), participated";
		$res = bo_db($sql);
		while ($row = $res->fetch_assoc())
		{
			$time = strtotime($row['time'].' UTC');
			$index = floor( ($time - $time_max + $hours_back * 3600) / 60 / $group_minutes);
			$tmp[$index][$row['participated']] = $row['cnt'];
		}

		for ($i = 0; $i < $hours_back * 60 / $group_minutes; $i++)
		{
			$X[$i] = $time_max + ($i * $group_minutes - $hours_back * 60) * 60;
			
			$all_strikes   = (double)@array_sum($tmp[$i]);
			$participated  = (double)$tmp[$i][1];
			$not_evaluated = (double)$tmp[$i][-1];
			
			$Y[$i]  = $all_strikes ? $participated / $all_strikes * 100 : 0;
			$Y2[$i] = $all_strikes ? ($participated+$not_evaluated) / $all_strikes * 100 : 0;
			$Y3[$i] = $all_strikes;
		}
		
		$graph_type = 'datlin';
		
		$ymin = 0;
		$ymax = 105;
	}
	else if ($type == 'spectrum' || $type == 'amplitudes' || $type == 'amplitudes_max')
	{
		$station_id = 0;
		
		$channels = BO_ANTENNAS;
		$last_uptime = bo_get_conf('uptime_raw'); //RAW-update time!!!!
		$time_max = time();
		$participated = intval($_GET['participated']);
		
		if ($hours_back > 24)
			$group_minutes *= ceil($hours_back / 24);
		
		$time_max = floor($time_max / 60 / $group_minutes) * 60 * $group_minutes; //round
		
		$amp_divisor = 10;
		
		if ($participated > 0)
		{
			$sql_join = "
					JOIN ".BO_DB_PREF."strikes s
					ON s.raw_id=r.id ";
			
			$add_title .= ' '._BL('with_strikes');
			
			if ($participated == 2)
			{
				$sql_join .= " AND $sql_part ";
				$add_title .= '+'._BL('with_participation');
			}
			
			$sql_join .= bo_region2sql($region);
		}
		else if ($participated < 0)
		{
			$sql_join = "
					LEFT JOIN ".BO_DB_PREF."strikes s
					ON s.raw_id=r.id ";
			$sql_where = " AND s.id IS NULL ";
			
			$add_title .= ' '._BL('without_strikes');
		}
		
		if ($type == 'spectrum')
		{
			$tmp = raw2array();
			$freqs = $tmp['spec_freq'];
			unset($freqs[0]);

			foreach($freqs as $freq_id => $freq)
				$f2id[$freq] = $freq_id;
			
			for ($channel=1;$channel<=$channels;$channel++)
			{
				foreach($freqs as $freq_id => $freq)
				{
					$Y[$channel][$freq_id] = 0;
					$Y2[$channel][$freq_id] = 0;
				}

				if (substr($type2,0,3) == 'amp')
				{
					if ($type2 == 'amp_max')
						$sname = "r.amp".$channel."_max";
					else if ($type2 == 'amp')
						$sname = "r.amp".$channel;
						
					$sname = "ABS(CONVERT($sname, SIGNED) - 128)*2/256*".BO_MAX_VOLTAGE;
				}
				else
					$sname = "r.freq".$channel."_amp";
				
				$sql = "SELECT r.freq".$channel." freq, SUM($sname) amp_sum, COUNT(r.id) cnt
						FROM ".BO_DB_PREF."raw r
						$sql_join
						WHERE 
							r.time BETWEEN '".gmdate('Y-m-d H:i:s', $time_max - 3600 * $hours_back)."' AND '".gmdate('Y-m-d H:i:s', $time_max)."'
							AND (r.amp".$channel."_max != 128)
						$sql_where
						GROUP BY freq";
				$res = bo_db($sql);
				while ($row = $res->fetch_assoc())
				{
					$freq_id = (int)$f2id[$row['freq']];
					$Y[$channel][$freq_id]  = $row['amp_sum'] / $row['cnt'];
					$Y2[$channel][$freq_id] = $row['cnt'];
				}
			}

			$tickLabels[0] = '0kHz';
			foreach($freqs as $freq_id => $freq)
			{
				$X[$freq_id] = $freq;
				$tickLabels[$freq_id] = $freq.'kHz';
			}
			
		}
		else
		{
			
			
			if ($type == 'amplitudes_max')
			{
				$ampmax = '_max';
				$type = 'amplitudes';
			}
			
			$caption = '';
			
			for ($channel=1;$channel<=$channels;$channel++)
			{
				for ($i=0;$i<256/$amp_divisor;$i++)
				{
					$Y[$channel][$i] = 0;
				}
				
				$amp_sum = 0;
				$sql = "SELECT ROUND(ABS(CONVERT(amp".$channel."$ampmax, SIGNED) - 128)*2 / $amp_divisor) amp, COUNT(r.id) cnt
						FROM ".BO_DB_PREF."raw r
						$sql_join
						WHERE r.time BETWEEN '".gmdate('Y-m-d H:i:s', $time_max - 3600 * $hours_back)."' AND '".gmdate('Y-m-d H:i:s', $time_max)."'
						$sql_where
						AND (r.amp".$channel."_max != 128)
						GROUP BY amp";
				$res = bo_db($sql);
				while ($row = $res->fetch_assoc())
				{
					$Y[$channel][$row['amp']] = $row['cnt'];
					$amp_sum += $row['amp'] * $row['cnt'];
				}
				
				$count = @array_sum($Y[$channel]);
				
				$caption .= _BL('Mean value channel').' '.$channel.': ';
				if (intval($count))
					$caption .= number_format(($amp_sum / $count * $amp_divisor) / 256 * BO_MAX_VOLTAGE, 3, _BL('.'), _BL(',')).'V';
				else
					$caption .= '?';
				
				$caption .= "\n";
			}
			
			foreach($Y[1] as $amp_id => $dummy)
			{
				$tickLabels[$amp_id] = number_format(($amp_id * $amp_divisor) / 256 * BO_MAX_VOLTAGE, 1, _BL('.'), _BL(',')).'V';
				$X[$amp_id] = $amp_id;
			}
		}

		$xmin = 0;
		$xmax = count($X);

		$graph_type = 'linlin';
		$tickLabels[] = '';
	}
	else if ($type == 'frequencies_time' || $type == 'amplitudes_time' || $type == 'amplitudes_max_time')
	{
		$value_max = isset($_GET['value_max']) ? intval($_GET['value_max']) : 0;
		$average = isset($_GET['average']);
		$participated = intval($_GET['participated']);
				
		$channels = BO_ANTENNAS;
		$last_uptime = bo_get_conf('uptime_raw');
		$time_max = time();
		
		if ($hours_back > 24)
			$group_minutes *= ceil($hours_back / 24);
		
		$time_max = floor($time_max / 60 / $group_minutes) * 60 * $group_minutes; //round
		
		if ($participated > 0)
		{
			$sql_join = "
					JOIN ".BO_DB_PREF."strikes s
					ON s.raw_id=r.id ";
			
			$add_title .= ' '._BL('with_strikes');
			
			if ($participated == 2)
			{
				$sql_join .= " AND $sql_part ";
				$add_title .= '+'._BL('with_participation');
			}
			
			$sql_join .= bo_region2sql($region);
		}
		else if ($participated < 0)
		{
			$sql_join = "
					LEFT JOIN ".BO_DB_PREF."strikes s
					ON s.raw_id=r.id ";
			$sql_where = " AND s.id IS NULL ";
			
			$add_title .= ' '._BL('without_strikes');
		}
		
		switch($type)
		{
			case 'frequencies_time':

				if ($average)
				{
					$values_text = '';
					$sql_select .= ", 0 extra, SUM(r.freq{CHANNEL}) extra_sum ";
				}
				else
				{
					if ($value < 0)
						$value = 0;

					if (!$value_max || $value_max < $value)
						$value_max = $value + BO_GRAPH_RAW_SPEC_MAX_X / 10;

					$values_text = $value.'-'.$value_max.'kHz';
					$sql_select .= ", r.freq{CHANNEL} BETWEEN '$value' AND '$value_max' extra ";
				}
				
					
				break;

			case 'amplitudes_max_time':
				$ampmax = '_max';
				$type = 'amplitudes_time';		
				
			case 'amplitudes_time':
			
			
				if ($average)
				{
					$values_text = '';
					$sql_select .= ", 0 extra, SUM(ABS(CONVERT(r.amp{CHANNEL}$ampmax, SIGNED) - 128)*2)/256*".BO_MAX_VOLTAGE." extra_sum ";
				}
				else
				{
					if ($value < 0)
						$value = 10;
				
					if (!$value_max || $value_max < $value)
						$value_max = $value + 10;

					$amp_min = $value / 10 / BO_MAX_VOLTAGE * 255;
					$amp_max = $value_max / 10 / BO_MAX_VOLTAGE * 255;
						
					$values_text = number_format($value/10, 1, _BL('.'), _BL(',')).'-'.number_format($value_max/10, 1, _BL('.'), _BL(',')).'V';
					$sql_select .= ", ABS(CONVERT(r.amp{CHANNEL}$ampmax, SIGNED) - 128)*2 BETWEEN '$amp_min' AND '$amp_max' extra ";
				}
					
				break;
				
		}
		
		$count = array();
		
		for ($channel=1;$channel<=$channels;$channel++)
		{
			$tmp = array();
			$sql = "SELECT r.time time, COUNT(r.id) cnt $sql_select
					FROM ".BO_DB_PREF."raw r
					$sql_join
					WHERE r.time BETWEEN '".gmdate('Y-m-d H:i:s', $time_max - 3600 * $hours_back)."' AND '".gmdate('Y-m-d H:i:s', $time_max)."'
					$sql_where
					AND (r.amp".$channel."_max != 128)
					GROUP BY FLOOR(UNIX_TIMESTAMP(r.time) / 60 / $group_minutes), extra";
			
			$sql = strtr($sql, array('{CHANNEL}' => $channel));
			
			$res = bo_db($sql);
			while ($row = $res->fetch_assoc())
			{
				$time = strtotime($row['time'].' UTC');
				$index = floor( ($time - $time_max + $hours_back * 3600) / 60 / $group_minutes);
				$tmp[$index][$row['extra']] = $row['cnt'];
				
				if ($average)
					$extra_sum[$index] = $row['extra_sum'];
			}
				
			$count[$channel] = 0;
			
			for ($i = 0; $i < $hours_back * 60 / $group_minutes; $i++)
			{
				$X[$i] = $time_max + ($i * $group_minutes - $hours_back * 60) * 60;
				
				$cnt_all      = (double)@array_sum($tmp[$i]);
				$cnt_selected = (double)$tmp[$i][1];
				
				$count[$channel] += $cnt_all;
				
				if ($average)
				{
					$Y[$channel][$i]  = $cnt_all ? (double)$extra_sum[$i] / $cnt_all : 0;
					$Y2[$channel][$i]  = $cnt_all;
				}
				else
				{
					$Y[$channel][$i] = $cnt_all ? $cnt_selected / $cnt_all * 100 : 0;
					$Y2[$channel][$i]  = $cnt_selected;
				}
				
			}
		}

		$graph_type = 'datlin';
		
		if (!$average)
		{
			$ymin = 0;
			$ymax = 105;
		}
		else
			$type .= '_average';
		
	}
	else if ($type == 'strikes_station_residual_time')
    {
        date_default_timezone_set('UTC');

        class Timestamp {

            private $seconds;
            private $nanoseconds;

            public function __construct($seconds, $nanoseconds) {
                $this->seconds = $seconds;
                $this->nanoseconds = $nanoseconds;
            }

            public function isBefore($other) {
                if ($this->seconds == $other->seconds) {
                    return $this->nanoseconds < $other->nanoseconds;
                } else {
                    return $this->seconds < $other->seconds;
                }
            }

            public function usDifference($other) {
                return ($this->seconds - $other->seconds) * 1e6 + ($this->nanoseconds - $other->nanoseconds) * 1e-3;
            }

            public function __toString() {
                return gmdate('Y-m-d H:i:s', $this->seconds) . '.' . sprintf("%09d", $this->nanoseconds);
            }
        }

        $cVacuum = 0.299792458; # m / ns
        $cReduced = (1 - 0.0025) * $cVacuum;

        $time_max = time();
        $time_max = floor($time_max / 60) * 60 - 5 * 60; //round

        $binsize = 2.5;
        $range = 20;

        $X = array();
        for ($index = -$range; $index <= $range; $index++) {
            $X[] = $index * $binsize;
        }

        $Y = array_pad(array(), 2 * $range + 1, 0);


        $strikes_raw_sql = "SELECT s.time, s.time_ns, s.distance, r.time raw_time, r.time_ns raw_time_ns FROM strikes s
            INNER JOIN raw r ON s.raw_id = r.id
            WHERE s.time BETWEEN '" . gmdate('Y-m-d H:i:s', $time_max - 3600 * 12) .
            "' AND '" . gmdate('Y-m-d H:i:s', $time_max) . "';";
        $strikes_raw_res = bo_db($strikes_raw_sql);

        while ($strike_raw_row = $strikes_raw_res->fetch_assoc()) {

            $strike_time = new Timestamp(strtotime($strike_raw_row['time']), $strike_raw_row['time_ns']);
            $raw_time = new Timestamp(strtotime($strike_raw_row['raw_time']) + 1, $strike_raw_row['raw_time_ns']);

            $dt = $raw_time->usDifference($strike_time);

            $distance = $strike_raw_row['distance'];
            $runtime = $distance / $cReduced / 1000.0;

            $difference = $dt - $runtime;

            $index = intval($difference / $binsize) + $range;

            if ($index >= 0 && $index <= 2 * $range) {
                $Y[$index]++;
            }
        }

        $graph_type = 'linlin';
        $xmin = min($X);
        $xmax = max($X);
        $ymin = 0;
        $ymax = max($Y);
    }
    else
	{
		$interval = BO_UP_INTVL_STATIONS;
		if ($interval < 10)
			$interval = 10;
			
		$ticks = ($time_end - $time_start) / 60 / $interval;

		$stId = $station_id ? $station_id : $stId;

		if ($type == 'stations')
		{
			//$sql_where[0] = " station_id != 0 ";
			$sql_where[0] = " station_id != 0 AND (signalsh > 0 OR strikesh > 0) ";
		}
		else
		{
			$sql_where[0] = " station_id  = 0 "; // first!
			$sql_where[1] = " station_id  = '$stId' ";
			$sql_where[2] = " station_id != 0 ";
		}

		foreach($sql_where as $data_id => $sqlw)
		{

			//one SQL-Query for all graphs -> Query Cache should improve performance (if enabled)
			$sql = "SELECT time, AVG(signalsh) sig, AVG(strikesh) astr, MAX(strikesh) mstr, COUNT(time) / COUNT(DISTINCT time) cnt
					FROM ".BO_DB_PREF."stations_stat
					WHERE time BETWEEN '$date_start' AND '$date_end' AND $sqlw
					GROUP BY DAYOFMONTH(time)";
					
			if ($hours_back < 7 * 24)
					$sql .= ", HOUR(time), FLOOR(MINUTE(time) / ".$interval.")";

			$res = bo_db($sql);
			while($row = $res->fetch_assoc())
			{
				$time = strtotime($row['time'].' UTC');
				$index = floor( ($time - time() + 3600 * $hours_back) / 60 / $interval);

				if ($index < 0)
					continue;

				$Y[$data_id]['cnt'][$index] = $row['cnt']; //count
				$Y[$data_id]['sig'][$index]  = $row['sig'];  //average signals
				$Y[$data_id]['ssig'][$index] = $row['sig'] * $row['cnt'];  //sum of signals
				$Y[$data_id]['astr'][$index] = $row['astr']; //average strikes
				$Y[$data_id]['mstr'][$index] = $row['mstr']; //maximum strikes
				

				if ($data_id > 0)
				{
					//Strike Ratio
					if (intval($Y[0]['astr'][$index]))
						$Y[$data_id]['str_ratio'][$index] = $row['astr'] / intval($Y[0]['astr'][$index]) * 100;
					else
						$Y[$data_id]['str_ratio'][$index] = 0;

					//Signal Ratio
					if (intval($row['sig']))
						$Y[$data_id]['sig_ratio'][$index] = $row['astr'] / $row['sig'] * 100;
					else
						$Y[$data_id]['sig_ratio'][$index] = 0;
				}

				//Active stations
				$Y[$data_id]['ratio'][$index] = $row['mstr'];
			}

			for($i=0;$i<$ticks;$i++)
			{
				$X[$i] = $time_start + $i * $interval * 60;

				
				//special treatment for the following vars
				if (!isset($Y[$data_id]['sig_ratio'][$i]) && !isset($Y[$data_id]['str_ratio'][$i]) && $data_id > 0)
					$no_data_count++;
				else
					$no_data_count = 0;
				
				if ($no_data_count > 1)
				{
					$Y[$data_id]['sig'][$i] = 0;
					$Y[$data_id]['ssig'][$i] = 0;
					$Y[$data_id]['astr'][$i] = 0;
					$Y[$data_id]['mstr'][$i] = 0;
					$Y[$data_id]['cnt'][$i] = 0;
					$Y[$data_id]['sig_ratio'][$i] = null;
					$Y[$data_id]['str_ratio'][$i] = null;
				}
				else
				{
					//JpGraph wants equal number of x and y data points
					if (!isset($Y[$data_id]['sig'][$i])) $Y[$data_id]['sig'][$i] = $Y[$data_id]['sig'][$i-1];
					if (!isset($Y[$data_id]['ssig'][$i])) $Y[$data_id]['ssig'][$i] = $Y[$data_id]['ssig'][$i-1];
					if (!isset($Y[$data_id]['astr'][$i])) $Y[$data_id]['astr'][$i] = $Y[$data_id]['astr'][$i-1];
					if (!isset($Y[$data_id]['mstr'][$i])) $Y[$data_id]['mstr'][$i] = $Y[$data_id]['mstr'][$i-1];
					if (!isset($Y[$data_id]['cnt'][$i])) $Y[$data_id]['cnt'][$i] = $Y[$data_id]['cnt'][$i-1];
					if (!isset($Y[$data_id]['sig_ratio'][$i])) $Y[$data_id]['sig_ratio'][$i] = $Y[$data_id]['sig_ratio'][$i-1];
					if (!isset($Y[$data_id]['str_ratio'][$i])) $Y[$data_id]['str_ratio'][$i] = $Y[$data_id]['str_ratio'][$i-1];

				}
			}
		}

		if ($type == 'ratio')
		{
			$ymin = 0;
			$ymax = 100;
		}
		
		$graph_type = 'datlin';
	}

	$info_station_id = $station_id ? $station_id : $stId;

	if (!$title_no_hours)
		$add_title .= ' '._BL('of the last', true).' '.$hours_back.'h';

	$stInfo = bo_station_info($station_id);
	$city = $stInfo['city'];
	if ($station_id)
	{
		$add_title .= ' '._BL('for_station', true).': '.$city;
		bo_station_city(0, $stInfo['city']);
	}
	
	$caption = strtr($caption, array('{STATION_CITY}' => $city));

	
	//Display Windrose
	if (BO_GRAPH_STAT_RATIO_BEAR_WINDROSE === true 	&& 
			($type == 'ratio_bearing'  || $type == 'ratio_bearing_longtime')
		)
	{
		$title = _BL('graph_stat_title_ratio_bearing', true).$add_title;
		$size = BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_SIZE;
		
		$D = array();
		$D2 = array();
		foreach($Y as $i => $d)
		{
			$D[$i][0] = $d;
			$D2[$i] = $Y2[$i];
		}

		$I = bo_windrose($D, $D2, $size, null, array(), '', $bear_div, $title, !$station_id);
		
		header("Content-type: image/png");
		imagepng($I);
		exit;
	}

	if (empty($Y) && empty($Y2) && empty($X))
	{
		$Y[] = 0;
		$Y2[] = 0;
		$X[] = 0;
	}
		
	require_once 'jpgraph/jpgraph.php';
	require_once 'jpgraph/jpgraph_line.php';
	require_once 'jpgraph/jpgraph_bar.php';
	require_once 'jpgraph/jpgraph_plotline.php';
	require_once 'jpgraph/jpgraph_date.php';
	require_once 'jpgraph/jpgraph_log.php';

	$graph = new Graph(BO_GRAPH_STAT_W,BO_GRAPH_STAT_H,"auto");
	$graph->ClearTheme();
	$graph->SetScale($graph_type, $ymin, $ymax, $xmin, $xmax);

	switch($type)
	{

		case 'strikes_now':
			$plot=new LinePlot($Y, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_NOW_COLOR_L1);
			if (BO_GRAPH_STAT_STRIKES_NOW_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_NOW_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_NOW_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_strikes_now_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y2, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_NOW_COLOR_L2);
			if (BO_GRAPH_STAT_STRIKES_NOW_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_NOW_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_NOW_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_strikes_now_own'));
			$graph->Add($plot);

			$graph->yaxis->title->Set(_BL('Strike count per minute'));
			$graph->title->Set(_BL('graph_stat_title_strikes_now').$add_title);
			
			break;

			
		case 'participants_time':

			$plot=new LinePlot($Y, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_COLOR_L1);
			if (BO_GRAPH_STAT_STRIKES_PARTICIPANTS_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_participants_time_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y2, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_COLOR_L2);
			if (BO_GRAPH_STAT_STRIKES_PARTICIPANTS_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_WIDTH_2);
			$plot->SetLegend(strtr(_BL('graph_legend_participants_time_own'), array('{STATION_CITY}' => $city)));
			$graph->Add($plot);

			$plot=new LinePlot($Y3, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_COLOR_L3);
			if (BO_GRAPH_STAT_STRIKES_PARTICIPANTS_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_WIDTH_3);
			$plot->SetLegend(_BL('Strikes'));
			$graph->SetYScale(0,'lin');
			$graph->AddY(0,$plot);
			
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');
			$graph->ynaxis[0]->title->Set(_BL('Strikes'));
			$graph->title->Set(strtr(_BL('graph_stat_title_strikes_participants'), array('{PARTICIPANTS}' => $participants_text)).$add_title);
			
			break;

	case 'participants_time_average':

			$plot=new LinePlot($Y, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_COLOR_L1);
			if (BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_participants_time_avg_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y2, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_COLOR_L2);
			if (BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_WIDTH_2);
			$plot->SetLegend(strtr(_BL('graph_legend_participants_time_avg_own'), array('{STATION_CITY}' => $city)));
			$graph->Add($plot);

			$plot=new LinePlot($Y3, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_COLOR_L3);
			if (BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_PARTICIPANTS_AVG_WIDTH_3);
			$plot->SetLegend(_BL('Strikes'));
			$graph->SetYScale(0,'lin');
			$graph->AddY(0,$plot);
			
			$graph->yaxis->title->Set(_BL('Count'));
			$graph->ynaxis[0]->title->Set(_BL('Strikes'));
			$graph->title->Set(strtr(_BL('graph_stat_title_strikes_participants_avg'), array('{PARTICIPANTS}' => $participants_text)).$add_title);
			
			break;
			
		case 'deviations_time':

			$plot=new LinePlot($Y, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_COLOR_L1);
			if (BO_GRAPH_STAT_STRIKES_DEVIATIONS_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_DEVIATIONS_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_deviatinons_time_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y2, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_COLOR_L2);
			if (BO_GRAPH_STAT_STRIKES_DEVIATIONS_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_DEVIATIONS_WIDTH_2);
			$plot->SetLegend(strtr(_BL('graph_legend_deviations_time_own'), array('{STATION_CITY}' => $city)));
			$graph->Add($plot);

			$plot=new LinePlot($Y3, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_COLOR_L3);
			if (BO_GRAPH_STAT_STRIKES_DEVIATIONS_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_DEVIATIONS_WIDTH_3);
			$plot->SetLegend(_BL('Strikes'));
			$graph->SetYScale(0,'lin');
			$graph->AddY(0,$plot);
			
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');
			$graph->ynaxis[0]->title->Set(_BL('Strikes'));
			$graph->title->Set(strtr(_BL('graph_stat_title_strikes_deviations'), array('{DEVIATIONS}' => $deviations_text)).$add_title);
			
			break;
			
		case 'deviations_time_average':

			$plot=new LinePlot($Y, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_COLOR_L1);
			if (BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_deviatinons_time_avg_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y2, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_COLOR_L2);
			if (BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_WIDTH_2);
			$plot->SetLegend(strtr(_BL('graph_legend_deviations_time_avg_own'), array('{STATION_CITY}' => $city)));
			$graph->Add($plot);

			$plot=new LinePlot($Y3, $X);
			$plot->SetColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_COLOR_L3);
			if (BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_STRIKES_DEVIATIONS_AVG_WIDTH_3);
			$plot->SetLegend(_BL('Strikes'));
			$graph->SetYScale(0,'lin');
			$graph->AddY(0,$plot);
			
			$graph->yaxis->title->Set(_BL('Deviation').'  [m]');
			$graph->ynaxis[0]->title->Set(_BL('Strikes'));
			$graph->title->Set(strtr(_BL('graph_stat_title_strikes_deviations_avg'), array('{DEVIATIONS}' => $deviations_text)).$add_title);
			
			break;
			
		case 'strikes_time':

			$plot1=new BarPlot($Y);
			$plot1->SetColor(BO_GRAPH_STAT_STRIKES_TIME_COLOR_L1);
			if (BO_GRAPH_STAT_STRIKES_TIME_COLOR_F1)
				$plot1->SetFillColor(BO_GRAPH_STAT_STRIKES_TIME_COLOR_F1);
			$plot1->SetLegend(_BL('graph_legend_strikes_time_own'));

			$plot2=new BarPlot($Y2);
			$plot2->SetColor(BO_GRAPH_STAT_STRIKES_TIME_COLOR_L2);
			if (BO_GRAPH_STAT_STRIKES_TIME_COLOR_F2)
				$plot2->SetFillColor(BO_GRAPH_STAT_STRIKES_TIME_COLOR_F2);
			$plot2->SetLegend(_BL('graph_legend_strikes_time_all'));

			$plot = new AccBarPlot(array($plot1,$plot2), $X);
			if (BO_GRAPH_STAT_STRIKES_TIME_WIDTH)
				$plot->SetWidth(BO_GRAPH_STAT_STRIKES_TIME_WIDTH);

			$graph->Add($plot);
			$graph->xaxis->SetTickPositions($tickMajPositions,$tickPositions,$tickLabels);
			$graph->yaxis->title->Set(_BL('Count'));
			$graph->xaxis->title->Set(_BL($xtitle));
			$graph->title->Set(_BL('graph_stat_title_strikes_time').$add_title);
			
			break;

		case 'strikes':

			$graph->title->Set(_BL('graph_stat_title_strikes').$add_title);

			$plot=new LinePlot($Y[0]['astr'], $X);
			$plot->SetColor(BO_GRAPH_STAT_STR_COLOR_L1);
			if (BO_GRAPH_STAT_STR_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_STR_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_STR_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_strikes_sum'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[2]['astr'], $X);
			$plot->SetColor(BO_GRAPH_STAT_STR_COLOR_L3);
			if (BO_GRAPH_STAT_STR_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_STR_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_STR_WIDTH_3);
			$plot->SetLegend(_BL('graph_legend_strikes_avg_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[1]['astr'], $X);
			$plot->SetColor(BO_GRAPH_STAT_STR_COLOR_L2);
			if (BO_GRAPH_STAT_STR_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_STR_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_STR_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_strikes_own'));
			$graph->Add($plot);

			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Count per hour'));

			break;

		case 'signals':
			$graph->title->Set(_BL('graph_stat_title_signals').$add_title);

			$plot=new LinePlot($Y[2]['sig'], $X);
			$plot->SetColor(BO_GRAPH_STAT_SIG_COLOR_L1);
			if (BO_GRAPH_STAT_SIG_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_SIG_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_SIG_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_signals_avg_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[1]['sig'], $X);
			$plot->SetColor(BO_GRAPH_STAT_SIG_COLOR_L2);
			if (BO_GRAPH_STAT_SIG_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_SIG_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_SIG_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_signals_own'));
			$graph->Add($plot);

			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Count per hour'));
			break;
			
		case 'signals_all':

			$graph->title->Set(_BL('graph_stat_title_all_signals').$add_title);

			$plot=new LinePlot($Y[0]['ssig'], $X);
			$plot->SetColor(BO_GRAPH_STAT_SIG_COLOR_L2);
			if (BO_GRAPH_STAT_SIG_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_SIG_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_SIG_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_all_signals'));
			$graph->Add($plot);

			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Count per hour'));

			break;

		case 'ratio':
			$graph->title->Set(_BL('graph_stat_title_ratio').$add_title);

			$plot=new LinePlot($Y[2]['sig_ratio'], $X);
			$plot->SetColor(BO_GRAPH_STAT_RAT_COLOR_L1);
			if (BO_GRAPH_STAT_RAT_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_RAT_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_RAT_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_ratio_sig_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[1]['sig_ratio'], $X);
			$plot->SetColor(BO_GRAPH_STAT_RAT_COLOR_L2);
			if (BO_GRAPH_STAT_RAT_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_RAT_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_RAT_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_ratio_sig_own'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[2]['str_ratio'], $X);
			$plot->SetColor(BO_GRAPH_STAT_RAT_COLOR_L3);
			if (BO_GRAPH_STAT_RAT_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_RAT_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_RAT_WIDTH_3);
			$plot->SetLegend(_BL('graph_legend_ratio_str_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y[1]['str_ratio'], $X);
			$plot->SetColor(BO_GRAPH_STAT_RAT_COLOR_L4);
			if (BO_GRAPH_STAT_RAT_COLOR_F4)
				$plot->SetFillColor(BO_GRAPH_STAT_RAT_COLOR_F4);
			$plot->SetWeight(BO_GRAPH_STAT_RAT_WIDTH_4);
			$plot->SetLegend(_BL('graph_legend_ratio_str_own'));
			$graph->Add($plot);

			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');


			break;

		case 'distance':

			$graph->title->Set(_BL('graph_stat_title_distance').$add_title);

			$plot=new LinePlot($Y, $X);
			$plot->SetColor(BO_GRAPH_STAT_DIST_COLOR_L1);
			if (BO_GRAPH_STAT_DIST_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_DIST_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_DIST_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_distance_all'));
			$graph->Add($plot);

			$plot=new LinePlot($Y2, $X);
			$plot->SetColor(BO_GRAPH_STAT_DIST_COLOR_L2);
			if (BO_GRAPH_STAT_DIST_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_DIST_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_DIST_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_distance_own'));
			$graph->Add($plot);

			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Distance').'   [km]');

			break;

		case 'stations':

			$graph->title->Set(_BL('graph_stat_title_stations').$add_title);

			$plot=new LinePlot($Y[0]['cnt'], $X);
			$plot->SetColor(BO_GRAPH_STAT_STA_COLOR_L1);
			if (BO_GRAPH_STAT_STA_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_STA_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_STA_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_stations_active'));
			$graph->Add($plot);

			/*
			$plot=new LinePlot($Y[1]['cnt'], $X);
			$plot->SetColor(BO_GRAPH_STAT_STA_COLOR_L3);
			if (BO_GRAPH_STAT_STA_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_STA_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_STA_WIDTH_3);
			$plot->SetLegend(_BL('graph_legend_stations_active_signals'));
			$graph->Add($plot);

			$max_stations = bo_get_conf('longtime_count_max_active_stations');
			if ($max_stations)
			{
				$sline  = new PlotLine(HORIZONTAL, $max_stations, BO_GRAPH_STAT_STA_COLOR_L2, 1);
				$sline->SetWeight(BO_GRAPH_STAT_STA_WIDTH_2);
				$sline->SetLegend(_BL('graph_legend_stations_max_active'));
				$graph->AddLine($sline);

				$graph->yscale->SetAutoMax($max_stations + 1);
			}
			*/
			
			$max_stations = bo_get_conf('longtime_count_max_active_stations_sig');
			if ($max_stations)
			{
				$sline  = new PlotLine(HORIZONTAL, $max_stations, BO_GRAPH_STAT_STA_COLOR_L4, 1);
				$sline->SetWeight(BO_GRAPH_STAT_STA_WIDTH_4);
				$sline->SetLegend(_BL('graph_legend_stations_max_active_signal'));
				$graph->AddLine($sline);
			}

			// currently available stations
			$sql = "SELECT COUNT(*) cnt
					FROM ".BO_DB_PREF."stations
					WHERE status != '-'";
			$res = bo_db($sql);
			$row = $res->fetch_assoc();
			$available = $row['cnt'];

			if ($available)
			{
				$sline  = new PlotLine(HORIZONTAL, $available, BO_GRAPH_STAT_STA_COLOR_L2, 1);
				$sline->SetWeight(BO_GRAPH_STAT_STA_WIDTH_4);
				$sline->SetLegend(_BL('graph_legend_stations_available'));
				$graph->AddLine($sline);
			}
			
			
			$max = max($max_stations, $available);
			$graph->yscale->SetAutoMax($max+1);
			
			if ($max/2 < min($Y[0]['cnt']))
				$graph->yscale->SetAutoMin($max/2);
			
			$graph->xaxis->title->Set(_BL('Time'));
			$graph->yaxis->title->Set(_BL('Count'));

			break;

		case 'ratio_distance':
		case 'ratio_distance_longtime':

			$graph->title->Set(_BL('graph_stat_title_ratio_distance').$add_title);

			if (BO_GRAPH_STAT_RATIO_DIST_LINE)
			{
				$plot=new LinePlot($Y);
				$plot->SetWeight(BO_GRAPH_STAT_RATIO_DIST_WIDTH1);
			}
			else
			{
				$plot=new BarPlot($Y);
				if (BO_GRAPH_STAT_RATIO_DIST_WIDTH1)
					$plot->SetWidth(BO_GRAPH_STAT_RATIO_DIST_WIDTH1);
			}



			$plot->SetColor(BO_GRAPH_STAT_RATIO_DIST_COLOR_L1);
			if (BO_GRAPH_STAT_RATIO_DIST_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_RATIO_DIST_COLOR_F1);
			$plot->SetLegend(_BL('graph_legend_ratio_distance'));
			$graph->Add($plot);


			$plot=new LinePlot($Y2);
			$plot->SetColor(BO_GRAPH_STAT_RATIO_DIST_COLOR_L2);
			if (BO_GRAPH_STAT_RATIO_DIST_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_RATIO_DIST_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_RATIO_DIST_WIDTH2);
			$plot->SetLegend(_BL('graph_legend_count_distance'));
			$graph->SetYScale(0,'lin');
			$graph->AddY(0,$plot);

			$graph->xaxis->SetTickPositions($tickMajPositions,$tickPositions,$tickLabels);
			$graph->xaxis->title->Set(_BL('Distance').'   [km]');
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');

			$graph->ynaxis[0]->title->Set(_BL('Count'));

			break;

		case 'ratio_bearing':
		case 'ratio_bearing_longtime':

			$graph->title->Set(_BL('graph_stat_title_ratio_bearing').$add_title);

			if (BO_GRAPH_STAT_RATIO_BEAR_LINE)
			{
				$plot=new LinePlot($Y);
				$plot->SetWeight(BO_GRAPH_STAT_RATIO_BEAR_WIDTH1);
			}
			else
			{
				$plot=new BarPlot($Y);
				if (BO_GRAPH_STAT_RATIO_BEAR_WIDTH1)
					$plot->SetWidth(BO_GRAPH_STAT_RATIO_BEAR_WIDTH1);
			}

			$plot->SetColor(BO_GRAPH_STAT_RATIO_BEAR_COLOR_L1);
			if (BO_GRAPH_STAT_RATIO_BEAR_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_RATIO_BEAR_COLOR_F1);
			$plot->SetLegend(_BL('graph_legend_ratio_bearing'));
			$graph->Add($plot);


			$plot=new LinePlot($Y2);
			$plot->SetColor(BO_GRAPH_STAT_RATIO_BEAR_COLOR_L2);
			if (BO_GRAPH_STAT_RATIO_BEAR_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_RATIO_BEAR_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_RATIO_BEAR_WIDTH2);
			$plot->SetLegend(_BL('graph_legend_count_bearing'));
			$graph->SetYScale(0,'lin');
			$graph->AddY(0,$plot);

			$graph->xaxis->SetTickPositions($tickMajPositions,$tickPositions,$tickLabels);
			$graph->xaxis->title->Set(_BL('Bearing').'   [�]');
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');

			$graph->ynaxis[0]->title->Set(_BL('Count'));

			break;
		
		case 'participants':

			$plot1=new BarPlot($Y);
			$plot1->SetColor(BO_GRAPH_STAT_PARTICIPANTS_COLOR_L1);
			if (BO_GRAPH_STAT_PARTICIPANTS_COLOR_F1)
				$plot1->SetFillColor(BO_GRAPH_STAT_PARTICIPANTS_COLOR_F1);
			$plot1->SetLegend(_BL('graph_legend_participants_own'));

			$plot2=new BarPlot($Y2);
			$plot2->SetColor(BO_GRAPH_STAT_PARTICIPANTS_COLOR_L2);
			if (BO_GRAPH_STAT_PARTICIPANTS_COLOR_F2)
				$plot2->SetFillColor(BO_GRAPH_STAT_PARTICIPANTS_COLOR_F2);
			$plot2->SetLegend(_BL('graph_legend_participants_all'));

			$plot = new AccBarPlot(array($plot1,$plot2), $X);
			if (BO_GRAPH_STAT_PARTICIPANTS_WIDTH)
				$plot->SetWidth(BO_GRAPH_STAT_PARTICIPANTS_WIDTH);
				
			$graph->Add($plot);

			$plot=new LinePlot($Y3);
			$plot->SetColor(BO_GRAPH_STAT_PARTICIPANTS_COLOR_L3);
			if (BO_GRAPH_STAT_PARTICIPANTS_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_PARTICIPANTS_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_PARTICIPANTS_WIDTH2);
			$plot->SetLegend(_BL('graph_legend_participants_ratio'));
			$graph->SetYScale(0,'lin', 0, 110);
			$graph->AddY(0,$plot);

			$graph->yaxis->title->Set(_BL('Count'));
			$graph->xaxis->title->Set(_BL('Participants'));
			$graph->ynaxis[0]->title->Set(_BL('Ratio').' [%]');
			$graph->title->Set(_BL('graph_stat_title_participants').$add_title);
		
			break;

	case 'deviations':

			$plot1=new BarPlot($Y);
			$plot1->SetColor(BO_GRAPH_STAT_DEVIATIONS_COLOR_L1);
			if (BO_GRAPH_STAT_DEVIATIONS_COLOR_F1)
				$plot1->SetFillColor(BO_GRAPH_STAT_DEVIATIONS_COLOR_F1);
			$plot1->SetLegend(_BL('graph_legend_deviations_own'));

			$plot2=new BarPlot($Y2);
			$plot2->SetColor(BO_GRAPH_STAT_DEVIATIONS_COLOR_L2);
			if (BO_GRAPH_STAT_DEVIATIONS_COLOR_F2)
				$plot2->SetFillColor(BO_GRAPH_STAT_DEVIATIONS_COLOR_F2);
			$plot2->SetLegend(_BL('graph_legend_deviations_all'));

			$plot = new AccBarPlot(array($plot1,$plot2), $X);
			if (BO_GRAPH_STAT_DEVIATIONS_WIDTH)
				$plot->SetWidth(BO_GRAPH_STAT_DEVIATIONS_WIDTH);
				
			$graph->Add($plot);

			$plot=new LinePlot($Y3);
			$plot->SetColor(BO_GRAPH_STAT_DEVIATIONS_COLOR_L3);
			if (BO_GRAPH_STAT_DEVIATIONS_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_DEVIATIONS_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_DEVIATIONS_WIDTH2);
			$plot->SetLegend(_BL('graph_legend_deviations_ratio'));
			$graph->SetYScale(0,'lin', 0, 110);
			$graph->AddY(0,$plot);

			$graph->yaxis->title->Set(_BL('Count'));
			$graph->xaxis->title->Set(_BL('Deviations'));
			$graph->ynaxis[0]->title->Set(_BL('Ratio').' [%]');
			$graph->title->Set(_BL('graph_stat_title_deviations').$add_title);

			$graph->xaxis->SetTickLabels($tickLabels);
			$graph->xaxis->SetTextLabelInterval(2);
			$graph->xaxis->SetTextTickInterval(2);
			
			break;

		case 'evaluated_signals';
			
			$plot=new LinePlot($Y, $X);
			$plot->SetColor(BO_GRAPH_STAT_EVALUATED_SIGNALS_COLOR_L1);
			if (BO_GRAPH_STAT_EVALUATED_SIGNALS_COLOR_F1)
				$plot->SetFillColor(BO_GRAPH_STAT_EVALUATED_SIGNALS_COLOR_F1);
			$plot->SetWeight(BO_GRAPH_STAT_EVALUATED_SIGNALS_WIDTH_1);
			$plot->SetLegend(_BL('graph_legend_evaluated_signals_part_ratio'));
			$graph->Add($plot);

			$plot=new LinePlot($Y2, $X);
			$plot->SetColor(BO_GRAPH_STAT_EVALUATED_SIGNALS_COLOR_L2);
			if (BO_GRAPH_STAT_EVALUATED_SIGNALS_COLOR_F2)
				$plot->SetFillColor(BO_GRAPH_STAT_EVALUATED_SIGNALS_COLOR_F2);
			$plot->SetWeight(BO_GRAPH_STAT_EVALUATED_SIGNALS_WIDTH_2);
			$plot->SetLegend(_BL('graph_legend_evaluated_signals_part_all_ratio'));
			$graph->Add($plot);

			$plot=new LinePlot($Y3, $X);
			$plot->SetColor(BO_GRAPH_STAT_EVALUATED_SIGNALS_COLOR_L3);
			if (BO_GRAPH_STAT_EVALUATED_SIGNALS_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_EVALUATED_SIGNALS_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_EVALUATED_SIGNALS_WIDTH_3);
			$plot->SetLegend(_BL('Strikes'));
			$graph->SetYScale(0,'lin');
			$graph->AddY(0,$plot);
			
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');
			$graph->ynaxis[0]->title->Set(_BL('Count'));
			$graph->title->Set(_BL('graph_stat_title_evaluated_signals').$add_title);
				
		
			break;
		
		case 'spectrum':
		
			if ($channels == 1)
			{
				//Bars
				$plot=new BarPlot($Y[1]);
				$plot->SetColor('#fff@1');
				$plot->SetFillColor(BO_GRAPH_STAT_SPECTRUM_COLOR1);
				$plot->SetLegend(_BL('Channel').' 1');
				$plot->SetWidth(BO_GRAPH_STAT_SPECTRUM_WIDTH1);
				$graph->Add($plot);
				
				//lines (count)
				$plot=new LinePlot($Y2[1]);
				$plot->SetColor(BO_GRAPH_STAT_SPECTRUM_COLOR3);
				$plot->SetWeight(BO_GRAPH_STAT_SPECTRUM_WIDTH3);
				$plot->SetLegend(_BL('Channel').' 1 ('._BL('Count').')');
				$graph->AddY(0,$plot);
			}
			else if ($channels == 2)
			{
				//Bars
				$plot1=new BarPlot($Y[1]);
				$plot1->SetColor('#fff@1');
				$plot1->SetFillColor(BO_GRAPH_STAT_SPECTRUM_COLOR1);
				$plot1->SetLegend(_BL('Channel').' 1');
				$plot1->SetWidth(BO_GRAPH_STAT_SPECTRUM_WIDTH1);
				
				$plot2=new BarPlot($Y[2]);
				$plot2->SetColor('#fff@1');
				$plot2->SetFillColor(BO_GRAPH_STAT_SPECTRUM_COLOR2);
				$plot2->SetLegend(_BL('Channel').' 2');
				$plot2->SetWidth(BO_GRAPH_STAT_SPECTRUM_WIDTH1);

				$plot = new GroupBarPlot(array($plot1,$plot2), $X);
				$plot->SetWidth(BO_GRAPH_STAT_SPECTRUM_WIDTH2);
				$graph->Add($plot);
				
				//lines (count)
				$plot3=new LinePlot($Y2[1]);
				$plot3->SetColor(BO_GRAPH_STAT_SPECTRUM_COLOR3);
				$plot3->SetWeight(BO_GRAPH_STAT_SPECTRUM_WIDTH3);
				$plot3->SetLegend(_BL('Channel').' 1 ('._BL('Count').')');
				$graph->AddY(0,$plot3);

				$plot4=new LinePlot($Y2[2]);
				$plot4->SetColor(BO_GRAPH_STAT_SPECTRUM_COLOR4);
				$plot4->SetWeight(BO_GRAPH_STAT_SPECTRUM_WIDTH4);
				$plot4->SetLegend(_BL('Channel').' 2 ('._BL('Count').')');
				$graph->AddY(0,$plot4);
			}
			
			$graph->SetYScale(0,'lin');
			
			if (substr($type2,0,3) == 'amp')
			{
				$graph->yaxis->title->Set(_BL('Mean amplitude').'  [V]');
			}
			else
			{
				$graph->yaxis->HideLabels();
				$graph->yaxis->title->Set(_BL('graph_stat_spectrum_yaxis_title'));
			}
			
			$graph->xaxis->title->Set(_BL('Frequency').'  [kHz]');
			$graph->ynaxis[0]->title->Set(_BL('Signal count'));
			$graph->title->Set(_BL('graph_stat_title_spectrum').$add_title);

			$graph->xaxis->SetTickLabels($tickLabels);
			$graph->xaxis->SetTextLabelInterval(1);
			$graph->xaxis->SetTextTickInterval(1);
			
			break;
		
		
		case 'amplitudes':

			if ($channels == 1)
			{
				//Bars
				$plot=new BarPlot($Y[1]);
				$plot->SetColor('#fff@1');
				$plot->SetFillColor(BO_GRAPH_STAT_SPECTRUM_COLOR1);
				$plot->SetLegend(_BL('Channel').' 1');
				$plot->SetWidth(BO_GRAPH_STAT_SPECTRUM_WIDTH1);
				$graph->Add($plot);
				
				//lines (count)
				$plot=new LinePlot($Y2[1]);
				$plot->SetColor(BO_GRAPH_STAT_SPECTRUM_COLOR3);
				$plot->SetWeight(BO_GRAPH_STAT_SPECTRUM_WIDTH3);
				$plot->SetLegend(_BL('Channel').' 1 ('._BL('Count').')');
				$graph->AddY(0,$plot);
			}
			else if ($channels == 2)
			{
				//Bars
				$plot1=new BarPlot($Y[1]);
				$plot1->SetColor('#fff@1');
				$plot1->SetFillColor(BO_GRAPH_STAT_SPECTRUM_COLOR1);
				$plot1->SetLegend(_BL('Channel').' 1');
				$plot1->SetWidth(BO_GRAPH_STAT_SPECTRUM_WIDTH1);
				
				$plot2=new BarPlot($Y[2]);
				$plot2->SetColor('#fff@1');
				$plot2->SetFillColor(BO_GRAPH_STAT_SPECTRUM_COLOR2);
				$plot2->SetLegend(_BL('Channel').' 2');
				$plot2->SetWidth(BO_GRAPH_STAT_SPECTRUM_WIDTH1);

				$plot = new GroupBarPlot(array($plot1,$plot2), $X);
				$plot->SetWidth(BO_GRAPH_STAT_SPECTRUM_WIDTH2);
				$graph->Add($plot);

			}
			
			$graph->yaxis->title->Set(_BL('Signal count'));
			$graph->xaxis->title->Set(_BL('Amplitude').'  [V]');
			$graph->title->Set(_BL('graph_stat_title_amplitude').$add_title);

			$graph->xaxis->SetTickLabels($tickLabels);
			$graph->xaxis->SetTextLabelInterval(1);
			$graph->xaxis->SetTextTickInterval(1);
			
			break;
			
			
		case 'amplitudes_time':
		case 'frequencies_time':
		
			$plot=new LinePlot($Y[1], $X);
			$plot->SetColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_L1A);
			if (BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F1A)
				$plot->SetFillColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F1A);
			$plot->SetWeight(BO_GRAPH_STAT_SIGNALS_TIME_WIDTH_1A);
			$plot->SetLegend(_BL('graph_legend_signals_time_percent').' '._BL('Channel').' 1 ');
			$graph->Add($plot);

			if ($channels == 2)
			{
				$plot=new LinePlot($Y[2], $X);
				$plot->SetColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_L2A);
				if (BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F2A)
					$plot->SetFillColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F2A);
				$plot->SetWeight(BO_GRAPH_STAT_SIGNALS_TIME_WIDTH_2A);
				$plot->SetLegend(_BL('graph_legend_signals_time_percent').' '._BL('Channel').' 2 ');
				$graph->Add($plot);
			}
			
			$plot=new LinePlot($Y2[1], $X);
			$plot->SetColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_L1B);
			if (BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F1B)
				$plot->SetFillColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F1B);
			$plot->SetWeight(BO_GRAPH_STAT_SIGNALS_TIME_WIDTH_1B);
			$plot->SetLegend(_BL('Count').' '._BL('Channel').' 1 ');
			$graph->AddY(0,$plot);
			
			if ($channels == 2)
			{
				$plot=new LinePlot($Y2[2], $X);
				$plot->SetColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_L2B);
				if (BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F2B)
					$plot->SetFillColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F2B);
				$plot->SetWeight(BO_GRAPH_STAT_SIGNALS_TIME_WIDTH_2B);
				$plot->SetLegend(_BL('Count').' '._BL('Channel').' 2 ');
				
				$graph->AddY(0,$plot);
			}
			
			$graph->SetYScale(0,'lin');
			$graph->yaxis->title->Set(_BL('Percent').'   [%]');
			$graph->ynaxis[0]->title->Set(_BL('Signal count'));
			$graph->title->Set(strtr(_BL('graph_stat_title_'.$type), array('{VALUES}' => $values_text)).$add_title);
			
			break;

		case 'amplitudes_time_average':
		case 'frequencies_time_average':
			
			$plot=new LinePlot($Y[1], $X);
			$plot->SetColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_L1A);
			if (BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F1A)
				$plot->SetFillColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F1A);
			$plot->SetWeight(BO_GRAPH_STAT_SIGNALS_TIME_WIDTH_1A);
			$plot->SetLegend(_BL('graph_legend_'.$type).' '._BL('Channel').' 1 ');
			$graph->Add($plot);

			if ($channels == 2)
			{
				$plot=new LinePlot($Y[2], $X);
				$plot->SetColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_L2A);
				if (BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F2A)
					$plot->SetFillColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F2A);
				$plot->SetWeight(BO_GRAPH_STAT_SIGNALS_TIME_WIDTH_2A);
				$plot->SetLegend(_BL('graph_legend_'.$type).' '._BL('Channel').' 2 ');
				$graph->Add($plot);
			}
			
			$plot=new LinePlot($Y2[1], $X);
			$plot->SetColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_L3);
			if (BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F3)
				$plot->SetFillColor(BO_GRAPH_STAT_SIGNALS_TIME_COLOR_F3);
			$plot->SetWeight(BO_GRAPH_STAT_SIGNALS_TIME_WIDTH_3);
			$plot->SetLegend(_BL('Signal count'));
			$graph->AddY(0,$plot);

		
			if ($type == 'frequencies_time_average')
				$graph->yaxis->title->Set(_BL('Frequency').'  [kHz]');
			else
				$graph->yaxis->title->Set(_BL('Amplitude').'  [V]');

			$graph->SetYScale(0,'lin');
			$graph->ynaxis[0]->title->Set(_BL('Signal count'));
			$graph->title->Set(_BL('graph_stat_title_'.$type).$add_title);
			
			break;

        case 'strikes_station_residual_time':
            $plot=new BarPlot($Y, $X);
            $plot->SetColor('#fff@1');
            $plot->SetFillColor(BO_GRAPH_STAT_SPECTRUM_COLOR1);
            $graph->xaxis->title->Set(_BL('Residual time').'  [�s]');
            $plot->SetLegend('Restzeiten');
            $plot->SetWidth(10);
            $graph->Add($plot);

            break;
	}

	
	if (defined("BO_GRAPH_ANTIALIAS") && BO_GRAPH_ANTIALIAS)
		$graph->img->SetAntiAliasing();

	if (BO_GRAPH_STAT_COLOR_BACK)
		$graph->SetColor(BO_GRAPH_STAT_COLOR_BACK);

	if (BO_GRAPH_STAT_COLOR_BACK)
		$graph->SetMarginColor(BO_GRAPH_STAT_COLOR_MARGIN);

	if (BO_GRAPH_STAT_COLOR_FRAME)
		$graph->SetFrame(true, BO_GRAPH_STAT_COLOR_FRAME);
	else
		$graph->SetFrame(false);

	if (BO_GRAPH_STAT_COLOR_BOX)
		$graph->SetBox(true, BO_GRAPH_STAT_COLOR_BOX);
	else
		$graph->SetBox(false);

	
	if (defined('BO_OWN_COPYRIGHT_GRAPHS') && trim(BO_OWN_COPYRIGHT_GRAPHS))
		$copyright = strip_tags(BO_OWN_COPYRIGHT_GRAPHS);
	elseif (defined('BO_OWN_COPYRIGHT') && trim(BO_OWN_COPYRIGHT) && BO_OWN_COPYRIGHT_GRAPHS !== false)
		$copyright = strip_tags(BO_OWN_COPYRIGHT);
	else
		$copyright = '';
		
	if ($copyright)
	{
		$graph->footer->left->Set($copyright);
		$graph->footer->left->SetColor('#999999');
		$graph->footer->left->SetFont(FF_DV_SANSSERIF,FS_NORMAL,BO_OWN_COPYRIGHT_SIZE);

		$graph->SetMargin(50,50,20,75);
		$graph->legend->SetPos(0.5,0.95,"center","bottom");

	}
	else
	{
		$graph->SetMargin(50,50,20,70);
		$graph->legend->SetPos(0.5,0.99,"center","bottom");
	}
	
	$graph->legend->SetColumns(2);
	$graph->legend->SetFillColor(BO_GRAPH_STAT_COLOR_LEGEND_FILL);
	$graph->legend->SetColor(BO_GRAPH_STAT_COLOR_LEGEND_TEXT, BO_GRAPH_STAT_COLOR_LEGEND_FRAME);
	$graph->legend->SetFont(FF_DV_SANSSERIF,FS_NORMAL,7);

	$graph->title->SetFont(FF_DV_SANSSERIF, FS_BOLD, BO_GRAPH_STAT_FONTSIZE_TITLE);
	$graph->title->SetColor(BO_GRAPH_STAT_COLOR_TITLE);
	
	if ($caption)
	{
		$caption=new Text($caption,60,30); 
		$caption->SetFont(FF_DV_SANSSERIF,FS_NORMAL, 7);
		$caption->SetColor(BO_GRAPH_STAT_COLOR_CAPTION);
		$graph->AddText($caption);

	}
	
	$graph->xaxis->title->SetColor(BO_GRAPH_STAT_COLOR_YAXIS_TITLE);
	$graph->yaxis->title->SetColor(BO_GRAPH_STAT_COLOR_YAXIS_TITLE);
	$graph->yaxis->SetLabelMargin(3);
	$graph->yaxis->SetTitleMargin(35);
	$graph->xaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,BO_GRAPH_STAT_FONTSIZE_XAXIS);
	$graph->yaxis->SetFont(FF_DV_SANSSERIF,FS_NORMAL,BO_GRAPH_STAT_FONTSIZE_YAXIS);

	if (is_object($graph->ynaxis[0]))
	{
		$graph->ynaxis[0]->title->SetColor(BO_GRAPH_STAT_COLOR_YAXIS_TITLE);
		$graph->ynaxis[0]->SetLabelMargin(3);
		$graph->ynaxis[0]->SetTitleMargin(30);
		$graph->ynaxis[0]->SetFont(FF_DV_SANSSERIF,FS_NORMAL,BO_GRAPH_STAT_FONTSIZE_YAXIS);
		$graph->ynaxis[0]->SetTitleMargin(45);
	}
	
	if ($graph_type == 'datlin')
	{
		if ($X[count($X)-1] - $X[0] > 3600 * 350)
		{
			$graph->xaxis->title->Set(_BL('day'));
			$graph->xaxis->scale->SetDateFormat('d.m');
			$graph->xaxis->scale->SetDateAlign(DAYADJ_1);
			$graph->xaxis->scale->ticks->Set(3600*24*7,3600*24);
		}
		else if ($X[count($X)-1] - $X[0] > 3600 * 36)
		{
			$graph->xaxis->title->Set(_BL('day'));
			$graph->xaxis->scale->SetDateFormat('d.m');
			$graph->xaxis->scale->SetDateAlign(DAYADJ_1);
			$graph->xaxis->scale->ticks->Set(3600*24,3600*6);
		}
		else
		{
			$graph->xaxis->title->Set(_BL('timeclock'));
			$graph->xaxis->scale->SetDateFormat('H:i');
			$graph->xaxis->scale->ticks->Set(3600 * 2,1800);
			$graph->xaxis->scale->SetTimeAlign(MINADJ_15);
		}
	}
	
	BoDb::close();
	bo_session_close(true);
	
	header("Content-Type: image/png");
	header("Pragma: ");
	header("Cache-Control: public, max-age=".($time_end + BO_UP_INTVL_STATIONS * 60 - time()));
	header("Last-Modified: ".gmdate("D, d M Y H:i:s", $time_end)." GMT");
	header("Expires: ".gmdate("D, d M Y H:i:s", $time_end + BO_UP_INTVL_STATIONS * 60)." GMT");


	$I = $graph->Stroke(_IMG_HANDLER);
	imagepng($I);


}

function bo_graph_error($w=400, $h=300)
{
	$text = 'File "includes/jpgraph/jpgraph.php" not found!';
	bo_image_error($text, $w, $h);
}



function bo_windrose($D1, $D2 = array(), $size = 500, $einheit = null, $legend = array(), $sub = '', $dseg = 22.5, $title = '', $antennas = false)
{

	$pcircle = 0.85; // Anteil, welcher der Kreis im Bild einnimmt
	$pcmax = 0.9; // wie weit reicht max. teil an $pcircle heran
	$dtick = 45; //Bogenl�nge eines Segments der Skala �
	//$dseg = 22.5; //Bogenl�nge eines Segments der Balken
	$csize = 0.1; //Anteil, die Windstille haben soll

	$aborder = $dseg >= 22.5 && $dseg < 360 && $size > 100;
	$atext = $size > 100;
	$alegend = $size > 100;
	
	//Standardwerte berechnen
	$xm = $size;
	$ym = $size;
	$fontsize = $size / 200 * 14;
	$div = count($D1);
	$sum = 0;
	$rsize = $size * $pcircle * 2;
	$psize = $rsize * $pcmax;
	$lsize = $size * 0.4 * 2; 
	$size = $size * 2;

	
	//Calculations for the arcs
	foreach($D1 as $d)
	{
		$max = max(array_sum($d), $max);
		$sum += array_sum($d);
	}
	
	//Create the pic
	if (empty($legend))
		$lsize = 0;

	$I = imagecreatetruecolor($size + $lsize, $size);

	
	//Colors
	$Cwhite = imagecolorallocate($I, 255,255,255);
	$Cblack = imagecolorallocate($I, 0,0,0);
	$Cgrey  = imagecolorallocate($I, 150,150,150);
	$Ctrans = imagecolorallocatealpha($I, 0,0,0,127);

	//Color for the arcs (only the first is used)
	$C[0] = bo_hex2rgb(BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_COLOR1);

	
	//Styles
	$Sdotted1 = array($Cgrey, $Ctrans);
	$Sdotted2 = array($Cgrey,$Cgrey,$Cgrey, $Ctrans,$Ctrans,$Ctrans);

	imagefill($I, 0, 0, bo_hex2color($I, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_COLOR_BACKGROUND));

	$color = imagecolorallocate($I, 150,150,150);
	imagearc($I, $xm, $ym, $rsize, $rsize, 0, 360, $color);

	//Himmelsrichtungen
	if ($atext)
	{
		$bfsize = $fontsize * BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_FONTSIZE_BEAR;
		$color = imagecolorallocate($I, 10,10,10);
		
		$dx = bo_imagetextwidth($bfsize, _BL('N')) / 2;
		bo_imagestring($I, $bfsize, $rsize * 0.595 - $dx, $rsize * 0.030, _BL('N'), $color);
		
		$dx = bo_imagetextwidth($bfsize, _BL('S')) / 2;
		bo_imagestring($I, $bfsize, $rsize * 0.595 - $dx, $rsize * 1.120, _BL('S'), $color);
		
		$dy = bo_imagetextheight($bfsize, _BL('W')) / 2;
		bo_imagestringright($I, $bfsize, $rsize * 0.075, $rsize * 0.595 - $dy, _BL('W'), $color);
		
		$dy = bo_imagetextheight($bfsize, _BL('E')) / 2;
		bo_imagestring($I, $bfsize, $rsize * 1.108, $rsize * 0.595 - $dy, _BL('E'), $color);
	}

	ksort($D1);
	//The PLOT
	$polyline = array();
	foreach($D1 as $i => $d)
	{
		$alpha = 360 / $div * $i + 180;

		
		krsort($d); //R�ckw�rts durchgehen!
		$startval = array_sum($d);
		$nr = count($d)-1;

		if ((double)$max)
		{		
			//the arcs
			foreach($d as $val)
			{
				$y = $startval / $max * $psize + $csize * (1-$startval / $max) * $psize;
				$beta = $dseg / 2;

				$color = imagecolorallocate($I, $C[$nr][0],$C[$nr][1],$C[$nr][2]);
				imagefilledarc($I, $xm, $ym, $y, $y, $alpha + 90 - $beta, $alpha + 90 + $beta, $color,  IMG_ARC_PIE);
				
				if ($aborder)
					imagefilledarc($I, $xm, $ym, $y, $y, $alpha + 90 - $beta, $alpha + 90 + $beta, $Cblack, IMG_ARC_PIE | IMG_ARC_EDGED | IMG_ARC_NOFILL);
				
				$nr--;
				$startval -= $val;
			}
		}
		
		//calculate the polyline
		if (!empty($D2) && intval(max($D2)))
		{
			$y = ($D2[$i]/max($D2) + $csize) * ($psize+2) / 2;
			list($px, $py) = bo_rotate(0, $y, $alpha, $xm, $ym);
			$polyline[] = $px ;
			$polyline[] = $py ;
		}
	}

	
	if (!empty($polyline))
	{
		imagesetthickness($I,BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_COUNT_WIDTH);
		$color = bo_hex2color($I, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_COLOR2);
		imagepolygon($I, $polyline, count($polyline)/2, $color);
		imagesetthickness($I,1);
	}
	
	

	//Lines
	imagesetstyle($I, $Sdotted2);
	for($i=0;$i<180;$i+=$dtick)
	{
		list($x1, $y1) = bo_rotate(0, -$rsize/2, $i, $xm, $ym);
		list($x2, $y2) = bo_rotate(0, $rsize/2, $i, $xm, $ym);
		imageline($I, $x1, $y1, $x2, $y2, IMG_COLOR_STYLED);
	}

	//Antennas
	$ant1 = bo_get_conf('antenna1_bearing');
	$ant2 = bo_get_conf('antenna2_bearing');
	imagesetthickness($I,BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_ANTENNA_WIDTH);
	
	if ($antennas && $ant1 !== '' && $ant1 !== null)
	{
		$color = bo_hex2color($I, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_ANTENNA1_COLOR);
		list($x1, $y1) = bo_rotate(0, -$rsize/2*1.14, $ant1, $xm, $ym);
		list($x2, $y2) = bo_rotate(0, $rsize/2*1.14, $ant1, $xm, $ym);
		imageline($I, $x1, $y1, $x2, $y2, $color);
	}
	
	if ($antennas && $ant2 !== '' && $ant2 !== null && BO_ANTENNAS == 2)
	{
		$color = bo_hex2color($I, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_ANTENNA2_COLOR);
		list($x1, $y1) = bo_rotate(0, -$rsize/2*1.14, $ant2, $xm, $ym);
		list($x2, $y2) = bo_rotate(0, $rsize/2*1.14, $ant2, $xm, $ym);
		imageline($I, $x1, $y1, $x2, $y2, $color);
	}

	imagesetthickness($I,1);
	
	//Circle in the center
	$y = $csize * $psize;
	imagefilledarc($I, $xm, $ym, $y, $y, 0, 360, $Cwhite, IMG_ARC_PIE );
	imagefilledarc($I, $xm, $ym, $y, $y, 0, 360, $Cblack, IMG_ARC_PIE | IMG_ARC_NOFILL);


	
	//Circles and Values
	$color1 = bo_hex2color($I, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_NUMBERS_COLOR1);
	$color2 = bo_hex2color($I, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_NUMBERS_COLOR2);
	
	imagesetstyle($I, $Sdotted1);
	$circles = 4;
	for($i=1; $i<=4;$i++)
	{
		$s = $i / $circles * $psize + ($csize * (1-$i/$circles)) * $psize;
		imagefilledarc ($I, $xm, $ym, $s, $s, 0, 360, IMG_COLOR_STYLED, IMG_ARC_NOFILL);
		
		list($x, $y) = bo_rotate(0, $s/2, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_NUMBERS_ANGLE1-180, $xm, $ym);
		$e = ($max * $i / $circles);
		$e = $e >= 10 ? round($e) : number_format($e,1,_BL('.'),_BL(','));
		$e .= '%';
		bo_imagestring($I, $fontsize * BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_NUMBERS_SIZE, $x, $y, $e, $color1);
		
		
		if (!empty($D2) && intval(max($D2)))
		{
			list($x, $y) = bo_rotate(0, $s/2, BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_NUMBERS_ANGLE2-180, $xm, $ym);
			$e = (max($D2) * $i / $circles);
			$e = $e >= 10 ? round($e) : number_format($e,1,_BL('.'),_BL(','));
			$e .= ' '._BL('strikes');
			bo_imagestring($I, $fontsize * BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_NUMBERS_SIZE, $x, $y, $e, $color2);
		}
		
		
	}
	
	

	//Legend (currently not used)
	if (!empty($legend) && $alegend)
	{
		$lxpos = $size * 1.04;
		$lypos = $size * 0.05;
		$ksize = count($legend) > 14 ? 0.5 : 1;
		
		foreach($legend as $i => $l)
		{
			if ($i == 0)
			{
				if ($l != '')
				{
					$color = imagecolorallocate($I, 10,10,10);
					bo_imagestring($I, $fontsize * 0.8, $lxpos, $lypos, "$l", $color);
					$lypos += $size * 0.09 * $ksize;
				}
					
				continue;
			}
			
			$nr = $i - 1;
			$color = imagecolorallocate($I, $C[$nr][0],$C[$nr][1],$C[$nr][2]);
		
			bo_imagestring($I, $fontsize * 0.6 * $ksize, $lxpos + $size * 0.09 * $ksize, $lypos - $size * 0.014 * $ksize, "$l", $Cblack);
			imagefilledrectangle ($I, $lxpos + $size * 0.02 * $ksize, $lypos - $size * 0.05 * $ksize, $lxpos + $size * 0.07 * $ksize, $lypos, $color);
			
			$lypos += $size * 0.06 * $ksize;
			
			if ($lypos > $size * 0.99)
				break;
		}
	
	
	}

	if ($atext)
	{
		//Copyright
		if (defined('BO_OWN_COPYRIGHT_GRAPHS') && trim(BO_OWN_COPYRIGHT_GRAPHS))
			$copyright = strip_tags(BO_OWN_COPYRIGHT_GRAPHS);
		elseif (defined('BO_OWN_COPYRIGHT') && trim(BO_OWN_COPYRIGHT) && BO_OWN_COPYRIGHT_GRAPHS !== false)
			$copyright = strip_tags(BO_OWN_COPYRIGHT);
		else
			$copyright = '';

		if ($copyright)
		{
			$color = imagecolorallocate($I, 128,128,128);
			$theight = bo_imagetextheight(BO_OWN_COPYRIGHT_SIZE * 2, $tbold, $copyright);
			bo_imagestring($I, BO_OWN_COPYRIGHT_SIZE*2, 2, $size - $theight - 2, $copyright, $color);
		}
		
		
		//Titel
		$color = imagecolorallocate($I, 20,20,20);
		bo_imagestring_max($I, $fontsize * BO_GRAPH_STAT_RATIO_BEAR_WINDROSE_TITLE_SIZE, $size * 0.03, $size * 0.03, $title, $color, $size * 0.2, true);
	}

	$T = imagecreatetruecolor(($size+$lsize)/2, $size/2);
	imagecopyresampled($T, $I, 0,0, 0,0, ($size+$lsize)/2,$size/2, $size+$lsize,$size);

	return $T;

}

function bo_rotate($x,$y,$alpha, $xm=0,$ym=0)
{
	$alpha = $alpha / 180 * M_PI;
	
	$x2 = cos($alpha) * $x - sin($alpha) * $y + $xm;
	$y2 = sin($alpha) * $x + cos($alpha) * $y + $ym;
	
	return array($x2, $y2);
}

?>