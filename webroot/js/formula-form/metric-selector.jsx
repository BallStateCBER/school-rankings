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
      successfullyLoaded: false,
    };
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

      // Set up search
      let search = $('#jstree-search');
      let timeout = false;
      search.keyup(function() {
        if (timeout) {
          clearTimeout(timeout);
        }
        timeout = setTimeout(function() {
          const value = search.val();
          container.jstree(true).search(value);
        }, 250);
      });
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

  static getJsTreeConfig(data) {
    return {
      'core': {
        'data': Formatter.formatMetricsForJsTree(data.metrics),
        'check_callback': true,
      },
      'plugins': [
        'checkbox',
        'conditionalselect',
        'search',
        'sort',
        'wholerow',
      ],
      'checkbox': {
        'three_state': false,
      },
      'conditionalselect': function(node) {
        return node.data.selectable;
      },
      'search': {
        'show_only_matches': true,
        'show_only_matches_children': true,
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
      </div>
    );
  }
}

MetricSelector.propTypes = {
  context: PropTypes.string.isRequired,
};

export {MetricSelector};
