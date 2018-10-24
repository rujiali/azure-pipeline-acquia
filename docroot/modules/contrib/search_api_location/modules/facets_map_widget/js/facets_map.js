/**
 * @file
 * Provides the faceted map functionality.
 */

(function ($) {

  'use strict';

  Drupal.behaviors.mapFacets = {
    attach: function () {
      L.Heatmap = L.GeoJSON.extend({
        options: {
          type: 'clusters'
        },

        initialize: function () {
          var _this = this;
          _this._layers = {};
          _this._getData();
        },

        onAdd: function (map) {
          var _this = this;
          // Call the parent function
          L.GeoJSON.prototype.onAdd.call(_this, map);
          map.on('moveend', function () {
            _this.clusterMarkers.clearLayers();
            window.location.href = drupalSettings.facets.map.url.replace('__GEOM__', _this._mapViewToWkt() + hash['lastHash']);
            _this._getData();
          });
        },

        _computeHeatmapObject: function () {
          var _this = this;
          _this.facetHeatmap = {};
          var facetHeatmapArray = JSON.parse(drupalSettings.facets.map.results);
          // Convert array to an object
          $.each(facetHeatmapArray, function (index, value) {
            if ((index + 1) % 2 !== 0) {
              // Set object keys for even items
              _this.facetHeatmap[value] = '';
            }
            else {
              // Set object values for odd items
              _this.facetHeatmap[facetHeatmapArray[index - 1]] = value;
            }
          });
          this._computeIntArrays();
        },

        _createClusters: function () {
          var _this = this;
          _this.clusterMarkers = new L.MarkerClusterGroup({
            maxClusterRadius: 140
          });
          $.each(_this.facetHeatmap.counts_ints2D, function (row, value) {
            if (value === null) {
              return;
            }

            $.each(value, function (column, val) {
              if (val === 0) {
                return;
              }

              var bounds = new L.LatLngBounds([
                [_this._minLat(row), _this._minLng(column)],
                [_this._maxLat(row), _this._maxLng(column)]
              ]);
              _this.clusterMarkers.addLayer(new L.Marker(bounds.getCenter(), {
                count: val
              }).bindPopup(val.toString()));
            });
          });
          map.addLayer(_this.clusterMarkers);
        },

        _computeIntArrays: function () {
          var _this = this;
          _this.lengthX = (_this.facetHeatmap.maxX - _this.facetHeatmap.minX) / _this.facetHeatmap.columns;
          _this.lengthY = (_this.facetHeatmap.maxY - _this.facetHeatmap.minY) / _this.facetHeatmap.rows;
          _this._createClusters();
        },

        _minLng: function (column) {
          return this.facetHeatmap.minX + (this.lengthX * column);
        },

        _minLat: function (row) {
          return this.facetHeatmap.maxY - (this.lengthY * row) - this.lengthY;
        },

        _maxLng: function (column) {
          return this.facetHeatmap.minX + (this.lengthX * column) + this.lengthX;
        },

        _maxLat: function (row) {
          return this.facetHeatmap.maxY - (this.lengthY * row);
        },

        _getData: function () {
          var _this = this;
          _this._computeHeatmapObject();
        },

        /**
         * Provides the bounding box coordinates of map viewport.
         */
        _mapViewToWkt: function () {
          if (this._map === undefined) {
            return '["-180 -90" TO "180 90"]';
          }
          var bounds = this._map.getBounds();
          var wrappedSw = bounds.getSouthWest().wrap();
          var wrappedNe = bounds.getNorthEast().wrap();
          return '["' + wrappedSw.lng + ' ' + bounds.getSouth() + '" TO "' + wrappedNe.lng + ' ' + bounds.getNorth() + '"]';
        }
      });

      L.heatmap = function (options) {
        return new L.Heatmap(options);
      };

      /**
       * Check if L.MarkerCluster is included.
       */
      if (typeof L.MarkerCluster !== 'undefined') {
        L.MarkerCluster.prototype.initialize = function (group, zoom, a, b) {
          L.Marker.prototype.initialize.call(this, a ? (a._cLatLng || a.getLatLng()) : new L.LatLng(0, 0), {icon: this});
          this._group = group;
          this._zoom = zoom;
          this._markers = [];
          this._childClusters = [];
          this._childCount = 0;
          this._iconNeedsUpdate = true;
          this._bounds = new L.LatLngBounds();
          if (a) {
            this._addChild(a);
          }
          if (b) {
            this._addChild(b);
            this._childCount = b.options.count;
          }
        };
      }

      var map = L.map(drupalSettings.facets.map.id).setView([0, 0], 1);
      var hash = new L.Hash(map);
      L.tileLayer('http://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, &copy; <a href="http://cartodb.com/attributions">CartoDB</a>'
      }).addTo(map);
      L.heatmap({type: 'clusters'}).addTo(map);
    }
  };

})(jQuery, Drupal);
