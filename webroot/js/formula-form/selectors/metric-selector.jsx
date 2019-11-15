import React from 'react';
import PropTypes from 'prop-types';
import {Formatter} from '../../metric-manager/formatter.js';
import {Button} from 'reactstrap';
import 'jstree';
import {MetricSorter} from '../../sort/metric-sorter.js';

class MetricSelector extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      errorMsg: '',
      hasError: false,
      loading: false,
      retrying: false,
      successfullyLoaded: false,
    };
    this.setupClickEvents = this.setupClickEvents.bind(this);
    this.clearMetricTree = this.clearMetricTree.bind(this);
    this.loadMetricTree = this.loadMetricTree.bind(this);
  }

  componentDidUpdate(prevProps) {
    if (prevProps.context !== this.props.context) {
      this.clearMetricTree();
      this.loadMetricTree();
    }
  }

  componentDidMount() {
    this.loadMetricTree();
  }

  clearMetricTree() {
    this.setState({successfullyLoaded: false});
    const jstree = $('#jstree').jstree(true);
    if (jstree !== false) {
      jstree.destroy();
    }
  }

  loadMetricTree() {
    this.setState({
      errorMsg: '',
      hasError: false,
      loading: true,
      retrying: false,
    });

    let tryCount = 0;
    const retryLimit = 3;
    const options = {
      method: 'GET',
      url: '/api/metrics/' + this.props.context + 's.json?no-hidden=1',
      dataType: 'json',
      timeout: 4000,
    };
    const onDone = (data) => {
      // Load jsTree
      const container = $('#jstree');
      container.jstree(MetricSelector.getJsTreeConfig(data));
      this.setState({
        loading: false,
        retrying: false,
        successfullyLoaded: true,
      });
      this.setupSearch();
      this.setupClickEvents();
    };
    const onFail = (jqXHR) => {
      console.log(jqXHR);
      if (jqXHR.statusText === 'timeout') {
        if (tryCount > 0) {
          this.setState({retrying: true});
        }
        tryCount++;
        if (tryCount <= retryLimit) {
          console.log('Retrying');
          $.ajax(options)
            .done(onDone)
            .fail(onFail);
          return;
        }
      }
      let errorMsg = 'Error loading metrics. This may be a temporary network error.';
      if (
        jqXHR &&
        jqXHR.hasOwnProperty('responseJSON') &&
        jqXHR.responseJSON &&
        jqXHR.responseJSON.hasOwnProperty('message')
      ) {
          errorMsg = jqXHR.responseJSON.message;
      }
      this.setState({
        errorMsg: errorMsg,
        hasError: true,
        loading: false,
        retrying: false,
      });
    };
    $.ajax(options)
      .done(onDone)
      .fail(onFail);
  }

  setupSearch() {
    const search = $('#jstree-search');
    let timeout = false;
    const container = $('#jstree');
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
    const container = $('#jstree');

    container.on('select_node.jstree', (node, selected) => {
      this.props.handleSelectMetric(node, selected);
    });

    container.on('deselect_node.jstree', (node, selected) => {
      this.props.handleUnselectMetric(node, selected);
    });

    // Allow single-clicking on a parent metric to expand its children
    container.on('click', '.jstree-anchor', (event) => {
      $('#jstree').jstree('toggle_node', $(event.target));
    });
  }

  static getJsTreeConfig(data) {
    return {
      core: {
        data: Formatter.formatMetricsForJsTree(data.metrics),
        check_callback: true,
        themes: {
          icons: false,
        },
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
      sort: function(node1, node2) {
        return MetricSorter.sort(this, node1, node2);
      },
    };
  }

  render() {
    const subject = this.props.context === 'school' ? 'schools' : 'school corporation';

    return (
      <div>
        {this.state.loading &&
          <span className="loading">
            {this.state.retrying &&
              <span>It&apos;s taking longer than usual to load {subject} metrics...</span>
            }
            {!this.state.retrying &&
              <span>Loading {subject} metrics...</span>
            }
            <img src="/jstree/themes/default/throbber.gif" alt="Loading..."
                 className="loading" />
          </span>
        }
        {this.state.hasError &&
          <p className="text-danger">
            {this.state.errorMsg}
            <Button type="button" color="secondary" size="sm" onClick={this.loadMetricTree}>
              Try again
            </Button>
          </p>
        }
        {this.state.successfullyLoaded &&
          <div className="input-group" id="jstree-search-container">
            <label htmlFor="jstree-search" className="sr-only">
              Search
            </label>
            <input type="text" className="form-control" id="jstree-search"
                   placeholder="Search..."/>
            <Button color="secondary" onClick={this.props.handleClearMetrics}>
              Clear selections
            </Button>
          </div>
        }
        <div id="jstree"></div>
      </div>
    );
  }
}

MetricSelector.propTypes = {
  context: PropTypes.string.isRequired,
  handleClearMetrics: PropTypes.func.isRequired,
  handleSelectMetric: PropTypes.func.isRequired,
  handleUnselectMetric: PropTypes.func.isRequired,
};

export {MetricSelector};
