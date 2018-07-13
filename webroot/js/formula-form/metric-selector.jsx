import React from 'react';
import PropTypes from 'prop-types';
import {Formatter} from '../metric-manager/formatter.js';
import 'jstree';

class MetricSelector extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      errorMsg: '',
      hasError: false,
      loading: false,
      selectedMetrics: [],
      successfullyLoaded: false,
    };
    this.setupClickEvents = this.setupClickEvents.bind(this);
  }

  componentDidMount() {
    this.setState({loading: true});

    $.ajax({
      method: 'GET',
      url: '/api/metrics/' + this.props.context + 's.json?no-hidden=1',
      dataType: 'json',
    }).done((data) => {
      // Load jsTree
      let container = $('#jstree');
      container.jstree(MetricSelector.getJsTreeConfig(data));
      this.setState({successfullyLoaded: true});
      this.setupSearch();
      this.setupClickEvents();
    }).fail((jqXHR) => {
      let errorMsg = 'Error loading metrics';
      if (jqXHR.hasOwnProperty('responseJSON')) {
        if (jqXHR.responseJSON.hasOwnProperty('message')) {
          errorMsg = jqXHR.responseJSON.message;
        }
      }
      this.setState({
        hasError: true,
        errorMsg: errorMsg,
      });
    }).always(() => {
      this.setState({loading: false});
    });
  }

  setupSearch() {
    let search = $('#jstree-search');
    let timeout = false;
    let container = $('#jstree');
    search.keyup(function() {
      if (timeout) {
        clearTimeout(timeout);
      }
      timeout = setTimeout(function() {
        const value = search.val();
        container.jstree(true).search(value);
      }, 250);
    });
  }

  setupClickEvents() {
    let container = $('#jstree');

    container.on('select_node.jstree', (node, selected) => {
      // Ignore non-selectable metrics
      if (!selected.node.data.selectable) {
        return;
      }

      let metric = {
        metricId: selected.node.data.metricId,
        dataType: selected.node.data.type,
        name: selected.node.data.name,
      };

      // Add parents to metric name
      for (let i = 0; i < selected.node.parents.length; i++) {
        const parentId = selected.node.parents[i];
        if (parentId === '#') {
          continue;
        }
        const jstree = container.jstree(true);
        const node = jstree.get_node(parentId);
        metric.name = node.text + ' > ' + metric.name;
      }

      // Add metric
      let selectedMetrics = this.state.selectedMetrics;
      selectedMetrics.push(metric);
      this.setState({selectedMetrics: selectedMetrics});
    });

    container.on('deselect_node.jstree', (node, selected) => {
      let selectedMetrics = this.state.selectedMetrics;
      const unselectedMetricId = selected.node.data.metricId;
      const filteredMetrics = selectedMetrics.filter(
          (metric) => metric.metricId !== unselectedMetricId
      );
      this.setState({selectedMetrics: filteredMetrics});
    });
  }

  static getJsTreeConfig(data) {
    return {
      core: {
        data: Formatter.formatMetricsForJsTree(data.metrics),
        check_callback: true,
      },
      plugins: [
        'checkbox',
        'conditionalselect',
        'search',
        'sort',
        'wholerow',
      ],
      checkbox: {
        three_state: false,
        // tie_selection: false,
      },
      conditionalselect: function(node) {
        return node.data.selectable;
      },
      search: {
        show_only_matches: true,
        show_only_matches_children: true,
      },
    };
  }

  render() {
    return (
      <div>
        {this.state.loading &&
          <span className="loading">
            Loading options...
            <img src="/jstree/themes/default/throbber.gif" alt="Loading..."
                 className="loading" />
          </span>
        }
        {this.state.hasError &&
          <p className="text-danger">{this.state.errorMsg}</p>
        }
        {this.state.successfullyLoaded &&
          <div className="form-group" id="jstree-search-container">
            <label htmlFor="jstree-search" className="sr-only">
              Search
            </label>
            <input type="text" className="form-control" id="jstree-search"
                   placeholder="Search..."/>
          </div>
        }
        <div id="jstree"></div>
        {this.state.selectedMetrics.map((metric, i) => {
          return (<p key={i}>{metric.name}</p>);
        })}
      </div>
    );
  }
}

MetricSelector.propTypes = {
  context: PropTypes.string.isRequired,
};

export {MetricSelector};
