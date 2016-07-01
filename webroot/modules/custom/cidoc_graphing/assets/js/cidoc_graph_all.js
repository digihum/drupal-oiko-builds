/**
 * Created by nathan on 01/07/2016.
 */

// We need to collect our datasets from the server.
var links = [];
var nodes = [];
d3.xhr("/graphing/data/cidoc-entities", function(error, data) {
  nodes = JSON.parse(data.response);
  nodes.forEach(function(item, index) {
    item.weight = 1;
  });
  setup();
});
d3.xhr("/graphing/data/cidoc-references", function (error, data) {
  references = JSON.parse(data.response);
  references.forEach(function (item, index) {
    link = {};
    link.source = item.domain;
    link.target = item.range;
    link.property = item.property;
    links.push(link);
  });
  setup();
});

/**
 * Do the magic!
 */
function setup() {
  // Don't actually set up until we have our datasets.
  if (links.length == 0 || nodes.length == 0) {
    return;
  }
  
  // Links reference the indices of the nodes in the nodes array.
  textLinks = links;
  links = [];
  textLinks.forEach(function(textLink, linkIndex) {
    indexLink = {
      source: -1,
      target: -1,
    };
    jQuery.each(nodes, function(nodeIndex, node) {
      if (node.name == textLink.source) {
        indexLink.source = nodeIndex;
        
        // Long-winded way to break if we have all we need.
        if (indexLink.source != -1 && indexLink.target != -1) {
          return false;
        }
      }
      if (node.name == textLink.target) {
        indexLink.target = nodeIndex;
        
        // Long-winded way to break if we have all we need.
        if (indexLink.source != -1 && indexLink.target != -1) {
          return false;
        }
      }
    });
    links.push(indexLink);
  });
  
  
  var width = 700, height = 1000;

  window.force = d3.layout.force()
    // .nodes(d3.values(nodes))
    .nodes(nodes)
    .links(links)
    .size([width, height])
    // .linkDistance(100)
    .charge(-1000)
    .on("tick", tick)
    .start();

  window.svg = d3.select(".region-content").append("svg")
    .attr("width", width)
    .attr("height", height);

// Per-type markers, as they don't inherit styles.
  svg.append("defs").selectAll("marker")
    .data(["event-group", "group-package", "event-package"])
    .enter().append("marker")
    .attr("id", function (d) {
      return d;
    })
    .attr("viewBox", "0 -5 10 10")
    .attr("refX", 15)
    .attr("refY", -1.5)
    .attr("markerWidth", 6)
    .attr("markerHeight", 6)
    .attr("orient", "auto")
    .append("path")
    .attr("d", "M0,-5L10,0L0,5");

  window.tip = d3.tip()
    .attr('class', 'd3-tip')
    .offset([-10, 0])
    .html(function (d) {
      return "<strong></strong> <span style='color:white'>" + d.name + " - " + d.bundle + "</span>";
    });

  svg.call(tip);

  window.path = svg.append("g").selectAll("path")
    .data(force.links())
    .enter().append("path")
    .attr("class", function(d) {
      return 'link';
    });

  window.circle = svg.append("g").selectAll("circle")
    .data(force.nodes())
    .enter().append("circle")
    .attr("r", 6)
    .attr('class', function(d) {
      bundle = d.bundle;
      bundle = bundle.replace(/ /g, '-');
      return bundle;
    })
    .on('mouseover', tip.show)
    .on('mouseout', tip.hide)
    .call(force.drag);

  window.text = svg.append("g").selectAll("text")
    .data(force.nodes())
    .enter().append("text")
    .attr("x", 8)
    .attr("y", ".31em")
    .attr("font-family", "sans-serif")
    .attr("font-size", function (d) {
      return "14px";
    })
    .text(function (d) {
      return d.name;
    })
    .attr("fulltext", function (d) {
      return d.name;
    })
    .attr("shorttext", "hover to see name");
}



// Use elliptical arc path segments to doubly-encode directionality.
function tick() {
  path.attr("d", linkArc);
  circle.attr("transform", transform);
  text.attr("transform", transform);
}

function linkArc(d) {
  var dx = d.target.x - d.source.x,
    dy = d.target.y - d.source.y,
    dr = 0;
  return "M" + d.source.x + "," + d.source.y + "A" + dr + "," + dr + " 0 0,1 " + d.target.x + "," + d.target.y;
}

function transform(d) {
  return "translate(" + d.x + "," + d.y + ")";
}
