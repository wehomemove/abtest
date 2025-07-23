#!/usr/bin/env node

/**
 * A/B Testing MCP Server
 * Allows Claude to interact with A/B testing experiments directly
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';
import axios from 'axios';

class AbTestingServer {
  constructor() {
    this.server = new Server(
      {
        name: 'abtest-server',
        version: '0.1.0',
      },
      {
        capabilities: {
          tools: {},
        },
      }
    );

    this.apiBaseUrl = process.env.ABTEST_API_URL || 'http://localhost:8000/api/ab-testing';
    this.setupToolHandlers();
  }

  setupToolHandlers() {
    this.server.setRequestHandler(ListToolsRequestSchema, async () => {
      return {
        tools: [
          {
            name: 'create_experiment',
            description: 'Create a new A/B test experiment',
            inputSchema: {
              type: 'object',
              properties: {
                name: {
                  type: 'string',
                  description: 'Unique experiment name (e.g., "checkout_flow_v2")',
                },
                description: {
                  type: 'string',
                  description: 'Human-readable description of the experiment',
                },
                variants: {
                  type: 'object',
                  description: 'Variant weights (e.g., {"control": 50, "variant_a": 50})',
                },
                target_applications: {
                  type: 'array',
                  items: { type: 'string' },
                  description: 'Applications to target (e.g., ["motus", "apollo"])',
                },
                traffic_percentage: {
                  type: 'number',
                  description: 'Percentage of traffic to include (0-100)',
                  default: 100,
                },
              },
              required: ['name', 'description', 'variants'],
            },
          },
          {
            name: 'get_experiment',
            description: 'Get experiment details and current results',
            inputSchema: {
              type: 'object',
              properties: {
                name: {
                  type: 'string',
                  description: 'Experiment name to retrieve',
                },
              },
              required: ['name'],
            },
          },
          {
            name: 'list_experiments',
            description: 'List all experiments with their status',
            inputSchema: {
              type: 'object',
              properties: {
                status: {
                  type: 'string',
                  enum: ['active', 'paused', 'completed', 'draft'],
                  description: 'Filter by experiment status (optional)',
                },
              },
            },
          },
          {
            name: 'track_event',
            description: 'Track an A/B test event for analysis',
            inputSchema: {
              type: 'object',
              properties: {
                experiment: {
                  type: 'string',
                  description: 'Experiment name',
                },
                event: {
                  type: 'string',
                  description: 'Event name (e.g., "conversion", "click", "signup")',
                },
                user_id: {
                  type: 'string',
                  description: 'User identifier (optional, uses session if not provided)',
                },
                properties: {
                  type: 'object',
                  description: 'Additional event properties',
                },
              },
              required: ['experiment', 'event'],
            },
          },
          {
            name: 'get_variant',
            description: 'Get the assigned variant for a user in an experiment',
            inputSchema: {
              type: 'object',
              properties: {
                experiment: {
                  type: 'string',
                  description: 'Experiment name',
                },
                user_id: {
                  type: 'string',
                  description: 'User identifier (optional, uses session if not provided)',
                },
              },
              required: ['experiment'],
            },
          },
          {
            name: 'update_experiment',
            description: 'Update experiment settings (traffic, status, etc.)',
            inputSchema: {
              type: 'object',
              properties: {
                name: {
                  type: 'string',
                  description: 'Experiment name to update',
                },
                status: {
                  type: 'string',
                  enum: ['running', 'paused', 'completed'],
                  description: 'New experiment status',
                },
                traffic_percentage: {
                  type: 'number',
                  description: 'Update traffic percentage (0-100)',
                },
                description: {
                  type: 'string',
                  description: 'Update experiment description',
                },
              },
              required: ['name'],
            },
          },
          {
            name: 'get_experiment_stats',
            description: 'Get detailed statistical analysis for an experiment',
            inputSchema: {
              type: 'object',
              properties: {
                name: {
                  type: 'string',
                  description: 'Experiment name',
                },
                metric: {
                  type: 'string',
                  description: 'Specific metric to analyze (optional)',
                },
              },
              required: ['name'],
            },
          },
        ],
      };
    });

    this.server.setRequestHandler(CallToolRequestSchema, async (request) => {
      const { name, arguments: args } = request.params;

      try {
        switch (name) {
          case 'create_experiment':
            return await this.createExperiment(args);
          case 'get_experiment':
            return await this.getExperiment(args.name);
          case 'list_experiments':
            return await this.listExperiments(args.status);
          case 'track_event':
            return await this.trackEvent(args);
          case 'get_variant':
            return await this.getVariant(args);
          case 'update_experiment':
            return await this.updateExperiment(args);
          case 'get_experiment_stats':
            return await this.getExperimentStats(args);
          default:
            throw new Error(`Unknown tool: ${name}`);
        }
      } catch (error) {
        return {
          content: [
            {
              type: 'text',
              text: `Error: ${error.message}`,
            },
          ],
        };
      }
    });
  }

  async createExperiment(args) {
    const response = await axios.post(`${this.apiBaseUrl}/experiments`, {
      name: args.name,
      description: args.description,
      variants: args.variants,
      target_applications: args.target_applications || ['all'],
      traffic_percentage: args.traffic_percentage || 100,
      status: 'draft',
    });

    return {
      content: [
        {
          type: 'text',
          text: `✅ Experiment "${args.name}" created successfully!\n\n` +
                `📊 Variants: ${Object.entries(args.variants).map(([k, v]) => `${k} (${v}%)`).join(', ')}\n` +
                `🎯 Applications: ${args.target_applications?.join(', ') || 'all'}\n` +
                `🚦 Status: Draft (use update_experiment to set to 'running')\n\n` +
                `Dashboard: /ab-testing/dashboard/${args.name}`,
        },
      ],
    };
  }

  async getExperiment(name) {
    const response = await axios.get(`${this.apiBaseUrl}/experiments/${name}`);
    const experiment = response.data;

    return {
      content: [
        {
          type: 'text',
          text: `📊 **Experiment: ${experiment.name}**\n\n` +
                `📝 Description: ${experiment.description}\n` +
                `🚦 Status: ${experiment.status}\n` +
                `📈 Traffic: ${experiment.traffic_percentage}%\n\n` +
                `**Variants:**\n${Object.entries(experiment.variants).map(([k, v]) => `• ${k}: ${v}%`).join('\n')}\n\n` +
                `**Applications:** ${experiment.target_applications.join(', ')}\n` +
                `**Created:** ${new Date(experiment.created_at).toLocaleDateString()}`,
        },
      ],
    };
  }

  async listExperiments(status) {
    const url = status 
      ? `${this.apiBaseUrl}/experiments?status=${status}`
      : `${this.apiBaseUrl}/experiments`;
    
    const response = await axios.get(url);
    const experiments = response.data;

    const experimentList = experiments.map(exp => 
      `• **${exp.name}** (${exp.status}) - ${exp.description}`
    ).join('\n');

    return {
      content: [
        {
          type: 'text',
          text: `📋 **A/B Test Experiments** ${status ? `(${status})` : ''}\n\n${experimentList}`,
        },
      ],
    };
  }

  async trackEvent(args) {
    const response = await axios.post(`${this.apiBaseUrl}/track`, {
      experiment: args.experiment,
      event: args.event,
      user_id: args.user_id,
      properties: args.properties || {},
    });

    return {
      content: [
        {
          type: 'text',
          text: `✅ Event tracked: "${args.event}" for experiment "${args.experiment}"` +
                (args.properties ? `\n📊 Properties: ${JSON.stringify(args.properties, null, 2)}` : ''),
        },
      ],
    };
  }

  async getVariant(args) {
    const response = await axios.post(`${this.apiBaseUrl}/variant`, {
      experiment: args.experiment,
      user_id: args.user_id,
    });

    return {
      content: [
        {
          type: 'text',
          text: `🎯 Variant for "${args.experiment}": **${response.data.variant}**`,
        },
      ],
    };
  }

  async updateExperiment(args) {
    const { name, ...updates } = args;
    const response = await axios.patch(`${this.apiBaseUrl}/experiments/${name}`, updates);

    const updateText = Object.entries(updates)
      .map(([key, value]) => `• ${key}: ${value}`)
      .join('\n');

    return {
      content: [
        {
          type: 'text',
          text: `✅ Experiment "${name}" updated:\n\n${updateText}`,
        },
      ],
    };
  }

  async getExperimentStats(args) {
    const response = await axios.get(`${this.apiBaseUrl}/results/${args.name}`);
    const stats = response.data;

    const variantStats = Object.entries(stats.variants)
      .map(([variant, data]) => 
        `• **${variant}**: ${data.assignments} users, ${data.conversions} conversions (${data.conversion_rate}%)`
      ).join('\n');

    return {
      content: [
        {
          type: 'text',
          text: `📈 **Statistics for "${args.name}"**\n\n` +
                `👥 Total Users: ${stats.total_assignments}\n` +
                `🎯 Total Conversions: ${stats.total_conversions}\n\n` +
                `**By Variant:**\n${variantStats}`,
        },
      ],
    };
  }

  async run() {
    const transport = new StdioServerTransport();
    await this.server.connect(transport);
    console.error('A/B Testing MCP server running on stdio');
  }
}

const server = new AbTestingServer();
server.run().catch(console.error);