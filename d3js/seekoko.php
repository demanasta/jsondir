<?php
if (!empty($_GET['sumfile'])) { 
            $sumfile = $_GET['sumfile']; 
            $sumfile = basename($sumfile);
       }
else {
        echo "<h1>Error.... no summary file selected</h1>";
}
?>

<!DOCTYPE html>
<meta charset="utf-8">
<head>

<style>

#sta-inf-table, th, td {
    border-collapse: collapse;
    border: 1px solid black;
    text-align: center;
}

.graticule {
  fill: none;
  stroke: #777;
  stroke-width: .5px;
  stroke-opacity: .5;
}

.land {
  fill: #222;
}

.boundary {
  fill: none;
  stroke: #fff;
  stroke-width: .5px;
}

.bar-lon {
  fill: steelblue;
}
.bar-lat {
  fill: red;
}
.bar-hgt {
  fill: green;
}
.axis text {
  font: 10px sans-serif;
}

.axis path,
.axis line {
  fill: none;
  stroke: #000;
  shape-rendering: crispEdges;
}

.x.axis path {
  display: none;
}

<!--http://stackoverflow.com/questions/18165533/how-to-draw-a-line-link-between-two-points-on-a-d3-map-based-on-latitude-lon-->

</style>

</head>
<body>

<h2># Station Information</h2>
<p class="stainf-info"></p>
<table id="sta-inf-table"></table>

<h2># Station Differences <i>A-Priori - A-Posteriori</i></h2>
<svg class="chart"></svg>

<h2># Baselines and Stations</h2>
<svg class="map"></svg>

<h2># Rms Values</h2>
<p class="rms-info"></p>
<svg class="rms"></svg>

<script src="http://d3js.org/d3.v3.min.js"></script>
<script src="http://d3js.org/d3.geo.projection.v0.min.js" charset="utf-8"></script>
<script src="http://d3js.org/topojson.v1.min.js"></script>
<script src="json-to-table.js"></script>
<script>

var width  = 960,
    height = 500,
    pt_rad = 2.5, /* radius of points on map */
    bl_wdt = 2,   /* baseline width */
    nm_fnt = 9;   /* point name font size */

var sta_inf_table;
    
var margin = {top: 20, right: 30, bottom: 30, left: 40};
width  = width - margin.left - margin.right,
height = height - margin.top - margin.bottom;

// needed for zoom
var scale0 = (width - 1) / 2 / Math.PI;

var projection = d3.geo.patterson()
    .scale(153)
    .translate([width / 2, height / 2])
    .precision(1);

var path = d3.geo.path().projection(projection);

var graticule = d3.geo.graticule();

var svg = d3.select(".map")
            .attr("width", width + margin.left + margin.right)
            .attr("height", height + margin.top + margin.bottom)
        .append("svg");

svg.append("path")
   .datum(graticule)
   .attr("class", "graticule")
   .attr("d", path);

// needed for zoom
var g          = svg.append("g");
var mapGroup   = g.append('g');
var imageGroup = g.append('g');

/* bar chart per station corrections */
var y_scale = d3.scale.linear().range([height, 0]).nice();
var x_scale = d3.scale.ordinal().rangeRoundBands([0, width], .1);
var xAxis = d3.svg.axis().scale(x_scale).orient("bottom");
var yAxis = d3.svg.axis().scale(y_scale).orient("left");
var chart = d3.select(".chart")
    .attr("width", width + margin.left + margin.right)
    .attr("height", height + margin.top + margin.bottom)
  .append("g")
    .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

/* bar chart per station rms */
var y_scale_rms = d3.scale.linear().range([height, 0]).nice();
var yAxis_rms = d3.svg.axis().scale(y_scale_rms).orient("left");
var rms_chart = d3.select(".rms")
    .attr("width", width + margin.left + margin.right)
    .attr("height", height + margin.top + margin.bottom)
  .append("g")
    .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

d3.json("world-50m.json", function(error, world) {
  if (error) throw error;
  
    // for non-zoom this should be svg (not g.*)
    mapGroup.insert("path", ".graticule")
            .datum(topojson.feature(world, world.objects.land))
            .attr("class", "land")
            .attr("d", path)

    mapGroup.insert("path", ".graticule")
            .datum(topojson.mesh(world, world.objects.countries, function(a, b) { return a !== b; }))
            .attr("class", "boundary")
            .attr("d", path); 

    d3.json('<?php echo $sumfile ?>', function(error, data) {
        if (error) throw error;

        /* Lets write the information for the stations */
        sta_inf_table = data["stainf"];
        available_but_unprocessed   = [];
        reference_but_not_reference = []
        var StaInfTable = ConvertJsonToTable(sta_inf_table, 'jsonTable');
        document.getElementById("sta-inf-table").innerHTML = StaInfTable;
        var sta_in_net = sta_inf_table.length
        var sta_analyzed = 0;
        for (var p of sta_inf_table) {
            if (   p["processed"] == "Yes" ) { sta_analyzed++; }
            if (   p["available"] == "Yes"
                && p["processed"] == "No" ) {
                var is_excluded = "No";
                if ( p["exclude"] == "Yes" ) { is_excluded = "Yes"; }
                available_but_unprocessed.push( {name: p["station"], excluded: is_excluded} )
            }
            if (   p["available"] == "Yes" 
                && p["processed"] == "Yes" 
                && p["reference"] == "Yes" 
                && p["used_as_reference"] == "No" ) {
                reference_but_not_reference.push(p["station"])
            }
        }
        
        d3.select(".stainf-info").append("text").text("Number of stations in network: " + sta_in_net + " of which " + sta_analyzed + " were processed" +
        " (~" + (sta_analyzed*100/sta_in_net).toFixed(1)+"%).\n");
        
        /* append a list with all available but unprocessed sites (if any) */
        if ( available_but_unprocessed.length > 0 ) {
            var available_but_unprocessed_text = ""
            available_but_unprocessed_text = "Available But Unprocessed Sites:<ul>"
            for (var i of available_but_unprocessed) {
                if ( i['excluded'] == 'Yes' ) {
                        available_but_unprocessed_text += ("<li>" + i.name + " (<i>excluded</i>) </li>");
                } else {
                        available_but_unprocessed_text += ("<li>" + i.name + "</li>");
                }
            }
            available_but_unprocessed_text += "</ul>"
            
            var div = document.createElement('div');
            div.innerHTML = available_but_unprocessed_text;
            document.getElementsByClassName("stainf-info")[0].appendChild(div);
        }
        
        /* append a list with rejected reference stations */
        if ( reference_but_not_reference.length > 0 ) {
            available_but_unprocessed_text = "Rejected Reference Sites:<ul>"
            for (var i of reference_but_not_reference) {
                available_but_unprocessed_text += ("<li>" + i + "</li>");
            }
            available_but_unprocessed_text += "</ul>"
            var div = document.createElement('div');
            div.innerHTML = available_but_unprocessed_text;
            document.getElementsByClassName("stainf-info")[0].appendChild(div);
        }
        
        pts = []; /* store points here {name, lon, lat} */
        var max_geo_cor = -500, min_geo_cor = 500;
        var max_geo_rms = -500;
        var max_sta_rms_3d = {"name":"", "val":-500},
            max_sta_rms_2d = {"name":"", "val":-500},
            mean_sta_rms   = {"lon":0, "lat":0, "hgt":0};
        
        /* Plot stations */
        imageGroup.selectAll("image")
                  .data( d3.entries(data["addneq_summary"]) )
                  .enter()
                  .append("circle")
                  .attr("r", pt_rad)
                  .attr("cx", function(d) {
                        pts.push( {"name":d.value["name"], "lon":d.value["lonest"], "lat":d.value["latest"] } );
                        
                        /* by the way, get me the min/max geo correction values */
                        if ( Math.min(d.value["loncor"], d.value["latcor"], d.value["hgtcor"]) < min_geo_cor ) {
                            min_geo_cor = Math.min(d.value["loncor"], d.value["latcor"], d.value["hgtcor"]);
                        }
                        if ( Math.max(d.value["loncor"], d.value["latcor"], d.value["hgtcor"]) > max_geo_cor ) {
                            max_geo_cor = Math.max(d.value["loncor"], d.value["latcor"], d.value["hgtcor"]);
                        }
                        if ( Math.max(d.value["lonrms"], d.value["latrms"], d.value["hgtrms"]) > max_geo_rms ) {
                            max_geo_rms = Math.max(d.value["lonrms"], d.value["latrms"], d.value["hgtrms"]);
                        }
                        sta_rms_3d = Math.sqrt(d.value["lonrms"]*d.value["lonrms"]+d.value["latrms"]*d.value["latrms"]+d.value["hgtrms"]*d.value["hgtrms"]);
                        sta_rms_2d = Math.sqrt(d.value["lonrms"]*d.value["lonrms"]+d.value["latrms"]*d.value["latrms"]);
                        if ( sta_rms_3d > max_sta_rms_3d.val ) {
                            max_sta_rms_3d = {"name":d.value["name"], "val":sta_rms_3d};
                        }
                        if ( sta_rms_2d > max_sta_rms_2d.val ) {
                            max_sta_rms_2d = {"name":d.value["name"], "val":sta_rms_2d};
                        }
                        mean_sta_rms.lon += d.value["lonrms"];
                        mean_sta_rms.lat += d.value["latrms"];
                        mean_sta_rms.hgt += d.value["hgtrms"];
                        return projection([d.value["lonest"], d.value["latest"]])[0];
                  })
                  .attr("cy", function(d) {
                        return projection([d.value["lonest"], d.value["latest"]])[1];
                  })
                  .attr("fill", function(d) {
                      if ( d.value["adj"] === "ESTIM" ) return "red";
                      if ( d.value["adj"] === "HELMR" ) return "orange";
                      return "green"
                  });
        
        /* scale the mean rms values */
        mean_sta_rms.lon /= pts.length;
        mean_sta_rms.lat /= pts.length;
        mean_sta_rms.hgt /= pts.length;
        
        /* names of stations */
        for (var p of pts) {
            imageGroup.append("text")
            .text(p.name.substring(0,4)) /* fuck the DOMES number */
            .attr("x", projection([p["lon"], p["lat"]])[0])
            .attr("y", projection([p["lon"], p["lat"]])[1])
            .attr("fill", "yellow")
            .attr("font-size", nm_fnt);
        }
        
        /* set the domain for the bar plot x-axis */
        station_names = [];
        for (var p of pts) { station_names.push(p["name"].substring(0,4)); }
        x_scale.domain(station_names);
        y_scale.domain([min_geo_cor, max_geo_cor]);
        y_scale_rms.domain([0, max_geo_rms]);
        var x_axis_interval = x_scale(pts[1].name.substring(0,4)) - x_scale(pts[0].name.substring(0,4));
        
        /* per station chart */
        chart.append("g")
            .attr("class", "x axis")
            .attr("transform", "translate(0," + height + ")")
            .call(xAxis)
            .selectAll("text")	
            .style("text-anchor", "end")
            .attr("dx", "-.8em")
            .attr("dy", ".15em")
            .attr("transform", function(d) { return "rotate(-65)" });
        rms_chart.append("g")
            .attr("class", "x axis")
            .attr("transform", "translate(0," + height + ")")
            .call(xAxis)
        .selectAll("text")	
            .style("text-anchor", "end")
            .attr("dx", "-.8em")
            .attr("dy", ".15em")
            .attr("transform", function(d) { return "rotate(-65)" });

        chart.append("g")
            .attr("class", "y axis")
            .call(yAxis)
        .append("text")
            .attr("transform", "rotate(-90)")
            .attr("y", 6)
            .attr("dy", ".71em")
            .style("text-anchor", "end")
            .text("Coordinate Corrections (m)");
        rms_chart.append("g")
            .attr("class", "y axis")
            .call(yAxis_rms)
        .append("text")
            .attr("transform", "rotate(-90)")
            .attr("y", 6)
            .attr("dy", ".71em")
            .style("text-anchor", "end")
            .text("Rms (m)");

        chart.selectAll(".bar") /* longtitude correction bars */
            .data(d3.entries(data["addneq_summary"]))
            .enter()
            .append("rect")
            .attr("class", "bar-lon")
            .attr("y", function(d) { return y_scale(Math.max(0, d.value["loncor"])); })
            .attr("x", function(d) {
                return x_scale(d.value["name"].substring(0,4));
            })
            .attr("height", function(d) { return Math.abs(y_scale(d.value["loncor"]) - y_scale(0)); })
            .attr("width", x_scale.rangeBand()/3.5);
        rms_chart.selectAll(".bar") /* longtitude correction bars rms */
            .data( d3.entries(data["addneq_summary"]) )
            .enter()
            .append("rect")
            .attr("class", "bar-lon")
            .attr("y", function(d) { return y_scale_rms(d.value["lonrms"]); })
            .attr("x", function(d) {
                return x_scale(d.value["name"].substring(0,4));
            })
            .attr("height", function(d) { return height - y_scale_rms(d.value["lonrms"]); })
            .attr("width", x_scale.rangeBand()/3.5);
            
        chart.selectAll(".bar") /* latitude correction bars */
            .data( d3.entries(data["addneq_summary"]) )
            .enter()
            .append("rect")
            .attr("class", "bar-lat")
            .attr("y", function(d) { return y_scale(Math.max(0, d.value["latcor"])); })
            .attr("x", function(d) {
                return x_scale(d.value["name"].substring(0,4)) + x_axis_interval / 3.5;
            })
            .attr("height", function(d) { return Math.abs(y_scale(d.value["latcor"]) - y_scale(0)); })
            .attr("width", x_scale.rangeBand()/3.5);
        rms_chart.selectAll(".bar") /* latitude correction bars rms */
            .data( d3.entries(data["addneq_summary"]) )
            .enter()
            .append("rect")
            .attr("class", "bar-lat")
            .attr("y", function(d) { return y_scale_rms(d.value["latrms"]); })
            .attr("x", function(d) {
                return x_scale(d.value["name"].substring(0,4)) + x_axis_interval / 3.5;
            })
            .attr("height", function(d) { return height - y_scale_rms(d.value["latrms"]); })
            .attr("width", x_scale.rangeBand()/3.5);
        
        chart.selectAll(".bar") /* hgt correction bars */
            .data( d3.entries(data["addneq_summary"]) )
            .enter()
            .append("rect")
            .attr("class", "bar-hgt")
            .attr("y", function(d) { return y_scale(Math.max(0, d.value["hgtcor"])); })
            .attr("x", function(d) {
                return x_scale(d.value["name"].substring(0,4)) + (2*x_axis_interval) / 3.5;
            })
            .attr("height", function(d) { return Math.abs(y_scale(d.value["hgtcor"]) - y_scale(0)); })
            .attr("width", x_scale.rangeBand()/3.5);
        rms_chart.selectAll(".bar") /* height correction bars rms */
            .data( d3.entries(data["addneq_summary"]) )
            .enter()
            .append("rect")
            .attr("class", "bar-hgt")
            .attr("y", function(d) { return y_scale_rms(d.value["hgtrms"]); })
            .attr("x", function(d) {
                return x_scale(d.value["name"].substring(0,4)) + (2*x_axis_interval) / 3.5;
            })
            .attr("height", function(d) { return height - y_scale_rms(d.value["hgtrms"]); })
            .attr("width", x_scale.rangeBand()/3.5);
        
        /* horizontal rule at chart, for y = 0 */
        chart.append("g")
            .attr("class", "y axis")
        .append("line")
            .attr("y1", y_scale(0))
            .attr("y2", y_scale(0))
            .attr("x1", 0)
            .attr("x2", width);
        
        /* horizontal rule at chart, for mean values */
        rms_chart.append("g")
        .append("line")
            .attr("y1", y_scale_rms(mean_sta_rms.lon))
            .attr("y2", y_scale_rms(mean_sta_rms.lon))
            .attr("x1", 0)
            .attr("x2", width)
            .attr("stroke", "blue");
        rms_chart.append("g")
        .append("line")
            .attr("y1", y_scale_rms(mean_sta_rms.lat))
            .attr("y2", y_scale_rms(mean_sta_rms.lat))
            .attr("x1", 0)
            .attr("x2", width)
            .attr("stroke", "red");
        rms_chart.append("g")
        .append("line")
            .attr("y1", y_scale_rms(mean_sta_rms.hgt))
            .attr("y2", y_scale_rms(mean_sta_rms.hgt))
            .attr("x1", 0)
            .attr("x2", width)
            .attr("stroke", "green");
        
        /* write down some info about rms statistics */
        d3.select(".rms-info").append("text").text("Max 3d Rms : "+(max_sta_rms_3d.val*1000.0).toFixed(1)+"(mm) at station "+max_sta_rms_3d.name+"\n" +
        "Max 2d Rms : "+(max_sta_rms_2d.val*1000.0).toFixed(1)+"(mm) at station "+max_sta_rms_2d.name+"\n" +
        "Mean Rms : longtitude "+(mean_sta_rms.lon*1000.0).toFixed(1)+", latitude "+(mean_sta_rms.lat*1000.0).toFixed(1)+", height "+(mean_sta_rms.lat*1000.0).toFixed(1)+" (mm).");
        
        /* plot baselines */
        imageGroup.selectAll("image")
            .data( d3.entries(data["amb_res_summary"]) )
            .enter()
            .append("line")
            .attr("x1", function(d) {
                base  = d.value["station1"]
                var p = pts.filter(function(obj) {return obj.name.substring(0,4) === base; })[0];
                return projection([p["lon"], p["lat"]])[0];
            })
            .attr("y1", function(d) {
                base  = d.value["station1"]
                var p = pts.filter(function(obj) {return obj.name.substring(0,4) === base; })[0];
                return projection([p["lon"], p["lat"]])[1];
            })
            .attr("x2", function(d) {
                base  = d.value["station2"]
                var p = pts.filter(function(obj) {return obj.name.substring(0,4) === base; })[0];
                return projection([p["lon"], p["lat"]])[0];
            })
            .attr("y2", function(d) {
                base  = d.value["station2"]
                var p = pts.filter(function(obj) {return obj.name.substring(0,4) === base; })[0];
                return projection([p["lon"], p["lat"]])[1];
            })
            .attr("stroke", "rgb(255,0,0)")
            .attr("stroke-width", bl_wdt);
    });

});

// zoom and pan
var zoom = d3.behavior.zoom()
    .on("zoom", function() {

        g.attr("transform","translate("+ 
            d3.event.translate.join(",")+")scale("+d3.event.scale+")");

        g.selectAll("circle")
            .attr("d", path.projection(projection))
            .attr("r", (pt_rad + 0.1) / d3.event.scale);
            
        g.selectAll("path")  
            .attr("d", path.projection(projection));

        g.selectAll("line")
            .attr("stroke-width", bl_wdt / d3.event.scale);
        
        g.selectAll("text")
            .attr("font-size", nm_fnt / d3.event.scale);
        
});

d3.select(self.frameElement).style("height", height + "px");
svg.call(zoom)

</script>
</body>
</html>
