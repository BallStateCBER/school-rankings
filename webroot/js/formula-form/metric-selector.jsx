import React from 'react';
import PropTypes from 'prop-types';
import {Formatter} from '../metric-manager/formatter.js';
import {Button} from 'reactstrap';
import 'jstree';

class MetricSelector extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      errorMsg: '',
      hasError: false,
      loading: false,
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
      this.props.handleSelectMetric(node, selected);
    });

    container.on('deselect_node.jstree', (node, selected) => {
      this.props.handleUnselectMetric(node, selected);
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
