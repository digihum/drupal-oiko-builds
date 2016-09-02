/**
 * We need to collect our datasets from the server.
 */
var links = [];
var nodes = [];
d3.xhr("/graphing/data/cidoc-entities", function (error, data) {
  nodes = JSON.parse(data.response);
  nodes.forEach(function (item, index) {
    item.weight = 1;
    item.text = item.name;
    item.name = item.id;
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
 * Makes a path curvy.
 *
 * This makes things clear when you have two directional paths between your two
 * nodes, pointing in opposite directions, since they would otherwise completely
 * overlap.
 *
 * @param d
 * @returns {string}
 */
function linkArc(d) {
  var dx = d.target.x - d.source.x,
    dy = d.target.y - d.source.y,
    dr = Math.sqrt(dx * dx + dy * dy);
  return "M" + d.source.x + "," + d.source.y + "A" + dr + "," + dr + " 0 0,1 " + d.target.x + "," + d.target.y;
}

function transform(d) {
  return "translate(" + d.x + "," + d.y + ")";
}

/**
 * Some helpful zoom & drag functions, to aid canvas interaction.
 */

function zoomed() {
  container.selectAll('g').attr("transform", "translate(" + d3.event.translate + ")scale(" + d3.event.scale + ")");
}

function dragstarted(d) {
  d3.event.sourceEvent.stopPropagation();
  d3.select(this).classed("dragging", true);
}

function dragged(d) {
  d3.select(this).attr("cx", d.x = d3.event.x).attr("cy", d.y = d3.event.y);
}

function dragended(d) {
  d3.select(this).classed("dragging", false);
}


/**
 * Do the magic!
 */
function setup() {
  // Don't actually set up until we have our datasets!
  if (links.length == 0 || nodes.length == 0) {
    return;
  }

  /**
   * Clear out nodes that aren't linked to anything.
   */
  keep = [];
  jQuery.each(links, function(index, link) {
    keep.push(link.source);
    keep.push(link.target);
  });
  linkedNodes = [];
  jQuery.each(nodes, function(index, node) {
    if (keep.indexOf(node.name) != -1) {
      linkedNodes.push(node);
    }
  });
  nodes = linkedNodes;

  // Clear out links that don't link to any node.
  var nodeids = [];
  jQuery.each(nodes, function(index, node) {
    nodeids.push(node.name);
  });
  var linkedLinks = [];
  jQuery.each(links, function(index, link) {
    if (nodeids.indexOf(link.source) != -1 && nodeids.indexOf(link.target) != -1) {
      linkedLinks.push(link);
    }
  });
  links = linkedLinks;

  /**
   * Re-jig our links so that they reference the node array indices.
   */
  textLinks = links;
  links = [];
  textLinks.forEach(function (textLink, linkIndex) {
    indexLink = {
      source: -1,
      target: -1,
      property: textLink.property,
    };
    jQuery.each(nodes, function (nodeIndex, node) {
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

  /**
   * Key things to set up our visualisation.
   * @type {number}
   */
  var width = 1500, height = 800;
  window.force = d3.layout.force()
    .nodes(nodes)
    .links(links)
    .size([width, height])
    // .linkDistance(100)
    .charge(-250)
    .on("end", end)
    .start();

  window.svg = d3.select(".region-content").append("svg")
    .attr("width", width)
    .attr("height", height);


  /**
   * Set up zoom and drag, to make the canvas easy to interact with.
   */
  window.container = svg.append('g');
  window.zoom = d3.behavior.zoom()
    .scaleExtent([0.1, 10])
    .on("zoom", zoomed);
  window.drag = d3.behavior.drag()
    .origin(function (d) {
      return d;
    })
    .on("dragstart", dragstarted)
    .on("drag", dragged)
    .on("dragend", dragended);

  /**
   * Add markers to our paths.
   * We can even specify different types of marker, in our data parameter.
   */
  svg.append("defs").selectAll("marker")
    .data(["end"])
    .enter().append("marker")
    .attr("id", function (d) {
      return d;
    })
    .attr("viewBox", "0 -5 10 10")
    .attr("refX", 15)
    .attr("refY", -1.5)
    .attr("markerWidth", 4)
    .attr("markerHeight", 4)
    .attr("orient", "auto")
    .append("path")
    .attr("d", "M0,-5L10,0L0,5")
    .call(drag);

  window.tip = d3.tip()
    .attr('class', 'd3-tip')
    .offset(function (d) {
      // We need to position slightly differently for paths / circles.
      if (typeof (d.property) !== 'undefined') {
        return [-10, 0];
      }
      else {
        return [-5, 0];
      }
    })
    .html(function (d) {
      message = '';
      if (typeof (d.property) !== 'undefined') {
        message = d.property;
        return '<strong></strong> <span style="color:white">' + message + '</span>';
      }
      if (typeof (d.bundle) !== 'undefined') {
        message = d.text + ' - ' + d.bundle;
        return '<strong></strong> <a href="/cidoc-entity/' + d.name +'"><span style="color:white">' + message + '</span></a>';
      }


    });

  svg.call(tip);
  svg.call(zoom);

  /**
   * Add our paths, circles, text to the canvas.
   */
  window.path = container.append("g").selectAll("path")
    .data(force.links())
    .enter().append("path")
    .attr("class", function (d) {
      property = d.property.replace(/ /g, '-');
      return 'link ' + property;
    })
    .attr('marker-end', function (d) {
      return "url(#end)";
    })
    .on('mouseover', tip.show)
    .call(drag);

  window.circle = container.append("g").selectAll("circle")
    .data(force.nodes())
    .enter().append("circle")
    .attr("r", 4)
    .attr('class', function (d) {
      bundle = d.bundle;
      bundle = bundle.replace(/ /g, '-');
      return bundle;
    })
    .on('mouseover', tip.show)
    .on('click', fade(0.1))
    .on('dblclick', fade(1))
    .call(force.drag);

  window.text = container.append("g").selectAll("text")
    .data(force.nodes())
    .enter().append("text")
    .attr("x", 8)
    .attr("y", ".31em")
    .attr("font-family", "sans-serif")
    .attr("font-size", function (d) {
      return "8px";
    })
    .text(function (d) {
      return d.text;
    })
    .attr("fulltext", function (d) {
      return d.text;
    })
    .attr("shorttext", "hover to see name")
    .call(drag);


  // Figure out which nodes are linked, and store it in a convenient way.
  window.linkedByIndex = {};
  links.forEach(function (link) {
    linkedByIndex[link.source.index + "," + link.target.index] = 1;
    links.forEach(function (sublink) {
      if (link.target.index == sublink.source.index) {
        linkedByIndex[link.source.index + "," + sublink.target.index] = 1;
      }
    });
  });
}

// This runs every time d3 does some rendering.
function end() {
  // Make our paths curvy, so they show directionality.
  path.attr("d", linkArc);

  circle.attr("transform", transform);
  text.attr("transform", transform);
}


/**
 * The functions in this section handle highlighting/fading.
 *
 */
function connectedTo(index) {
  var connections = [];
  links.forEach(function (link) {
    if (link.source.index == index || link.target.index == index) {
      connections.push(link.target.index);
    }
  });
  return connections;
}
function neighboring(a, b) {
  return linkedByIndex[a.index + "," + b.index];
}
function isConnected(a, b) {
  return linkedByIndex[a.index + "," + b.index] || linkedByIndex[b.index + "," + a.index] || a.index == b.index;
}
function fade(opacity) {
  return function (d) {
    circle.style("stroke-opacity", function (o) {
      thisOpacity = isConnected(d, o) ? 1 : opacity;
      this.setAttribute('fill-opacity', thisOpacity);
      return thisOpacity;
    });

    path.style("opacity", function (o) {
      thisOpacity = isConnected(d, o) || o.source === d || o.target === d ? 1 : opacity;
      if (thisOpacity < 1) {
        connections = connectedTo(d.index);
        var index = 0;
        while (index < connections.length) {
          if (o.source.index == connections[index]) {
            thisOpacity = 1;
          }
          else {
            // console.log('index: ' + o.source.index + ' does not match index: ' + connections[index]);
          }
          index++;
        }
      }
      return thisOpacity;
    });

    text.style("fill-opacity", function (o) {
      thisOpacity = isConnected(d, o) ? 1 : opacity;
      // this.setAttribute('fill-opacity', thisOpacity);
      return thisOpacity;
    });

  }
}
